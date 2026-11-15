<?php
/**
 * AralPal – General Utility Helpers
 * ----------------------------------
 * Stateless functions used across the application.
 */

if (!function_exists('e')) :

    /**
     * Escape a value for safe HTML output.
     *
     * @param  mixed $str  Value to escape.
     * @return string
     */
    function e(mixed $str): string
    {
        return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Perform an HTTP redirect and halt execution.
     *
     * @param string $url  Absolute or root-relative URL.
     */
    function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Store a one-time flash message in the session.
     *
     * @param string $key  Slot identifier (e.g. 'success', 'error').
     * @param string $msg  Human-readable message text.
     */
    function flash(string $key, string $msg): void
    {
        $_SESSION['flash'][$key] = $msg;
    }

    /**
     * Retrieve and clear a flash message from the session.
     *
     * @param  string      $key  Slot identifier.
     * @return string|null       The message, or null if none was set.
     */
    function get_flash(string $key): ?string
    {
        if (isset($_SESSION['flash'][$key])) {
            $msg = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $msg;
        }
        return null;
    }

    /**
     * Format a date/datetime string for human-readable display.
     *
     * @param  string $dt  Any date string parseable by strtotime().
     * @return string      e.g. "January 1, 2025 08:00 AM"
     */
    function format_date(string $dt): string
    {
        $ts = strtotime($dt);
        if ($ts === false) {
            return $dt;
        }
        return date('F j, Y g:i A', $ts);
    }

    /**
     * Build an HTML star-rating widget for a 1–5 scale.
     *
     * Returns a string of <span> elements; filled stars use the class
     * `star-filled` and empty stars use `star-empty`.
     *
     * @param  float $rating  Numeric rating between 0 and 5.
     * @return string         Safe HTML fragment.
     */
    function star_rating_html(float $rating): string
    {
        $html  = '<span class="star-rating" aria-label="Rating: ' . e(number_format($rating, 1)) . ' out of 5">';
        $full  = (int) floor($rating);
        $empty = 5 - $full;

        for ($i = 0; $i < $full; $i++) {
            $html .= '<span class="star-filled" aria-hidden="true">★</span>';
        }
        for ($i = 0; $i < $empty; $i++) {
            $html .= '<span class="star-empty" aria-hidden="true">☆</span>';
        }

        $html .= '</span>';
        return $html;
    }

endif;

// ── Booking limit helpers ─────────────────────────────────────────────────────

/**
 * The booking_limit value that means "unlimited" in subscription_plans.
 */
const BOOKING_LIMIT_UNLIMITED = 0;

/**
 * Returns the effective monthly booking limit for a student.
 *
 * Logic:
 *   1. Find all active, non-expired subscriptions for this user.
 *   2. Among those plans, return the highest booking_limit
 *      (most permissive plan wins when multiple are active).
 *   3. If booking_limit = 0 on any active plan, the student is unlimited.
 *   4. If the student has no active subscription, fall back to the Free
 *      Student plan limit (looked up by name so it stays in sync with
 *      whatever the admin sets).
 *
 * @param int $user_id  The student's user ID.
 * @return int          The booking limit (0 = unlimited).
 */
function get_student_booking_limit(int $user_id): int
{
    $db = get_db();

    // Most permissive active plan limit:
    //   • If any active plan has booking_limit = 0 (unlimited), return 0.
    //   • Otherwise return the highest non-zero limit across all active plans.
    // A single aggregate query handles all three cases correctly:
    //   all-unlimited → MIN = 0 → return 0
    //   mixed          → MIN = 0 → return 0
    //   all non-zero   → MIN > 0 → return MAX
    $stmt = $db->prepare("
        SELECT
            CASE WHEN MIN(sp.booking_limit) = 0 THEN 0
                 ELSE MAX(sp.booking_limit)
            END AS effective_limit
        FROM subscriptions s
        JOIN subscription_plans sp ON sp.id = s.plan_id
        WHERE s.user_id  = ?
          AND s.status   = 'active'
          AND s.end_date >= CURDATE()
    ");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    // fetch() returns a row even when no subscriptions exist (the CASE returns NULL).
    if ($row !== false && $row['effective_limit'] !== null) {
        return (int) $row['effective_limit'];
    }

    // No active subscription — apply Free Student limit as the fallback
    $free_stmt = $db->prepare("
        SELECT booking_limit
        FROM subscription_plans
        WHERE user_type  = 'Student'
          AND LOWER(plan_name) LIKE '%free%'
        ORDER BY price ASC
        LIMIT 1
    ");
    $free_stmt->execute();
    $free = $free_stmt->fetch();

    // Hard-coded safety net if the Free plan has been renamed or deleted
    return $free !== false ? (int) $free['booking_limit'] : 3;
}

/**
 * Counts a student's active bookings in the current calendar month.
 *
 * Counts bookings with status: pending, accepted, or completed.
 * Cancelled and declined bookings do NOT consume the monthly limit.
 * The count is based on created_at (when the booking was made), not
 * session_date (when the session takes place).
 *
 * @param int $user_id  The student's user ID.
 * @return int
 */
function count_student_bookings_this_month(int $user_id): int
{
    $stmt = get_db()->prepare("
        SELECT COUNT(*) FROM bookings
        WHERE student_id = ?
          AND status NOT IN ('cancelled', 'declined')
          AND YEAR(created_at)  = YEAR(CURDATE())
          AND MONTH(created_at) = MONTH(CURDATE())
    ");
    $stmt->execute([$user_id]);
    return (int) $stmt->fetchColumn();
}

/**
 * Returns the student's subscription tier as an integer for feature-gating.
 *
 * Tier levels (match the plan features defined in subscription_plans):
 *   0 = Free    (no active paid subscription, or only a Free plan)
 *   1 = Basic   (active Basic Student subscription)
 *   2 = Premium (active Premium Student subscription)
 *
 * Non-Student roles always return 2 so they are never restricted.
 * Reads from the database only — never trusts session or frontend input.
 * Uses plan_name matching (same approach as get_student_booking_limit)
 * so it stays correct even if the admin adjusts plan prices.
 *
 * @param int    $user_id    The user's ID.
 * @param string $role_name  The user's role ('Student', 'Tutor', 'Admin').
 * @return int  0 | 1 | 2
 */
function get_student_plan_tier(int $user_id, string $role_name): int
{
    // Tutors and Admins are never subject to Student plan restrictions
    if (strcasecmp($role_name, 'Student') !== 0) {
        return 2;
    }

    // Fetch the most permissive active Student plan by name
    $stmt = get_db()->prepare("
        SELECT sp.plan_name
        FROM subscriptions s
        JOIN subscription_plans sp ON sp.id = s.plan_id
        WHERE s.user_id    = ?
          AND s.status     = 'active'
          AND s.end_date  >= CURDATE()
          AND sp.user_type = 'Student'
        ORDER BY sp.booking_limit ASC    -- 0 (unlimited) = Premium sorts first
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if ($row === false) {
        return 0; // No active subscription → Free tier
    }

    $name = strtolower((string) ($row['plan_name'] ?? ''));

    if (str_contains($name, 'premium')) {
        return 2;
    }
    if (str_contains($name, 'basic')) {
        return 1;
    }
    // Any other paid plan (e.g. renamed plans, custom plans) with booking_limit=0 → Premium
    // Any other paid plan with a limit → Basic
    return 0; // Falls back to Free treatment if name doesn't match known tiers
}

/** Convenience: is the student on Basic or higher? */
function student_has_basic_or_above(int $user_id, string $role_name): bool
{
    return get_student_plan_tier($user_id, $role_name) >= 1;
}

/** Convenience: is the student on Premium? */
function student_has_premium(int $user_id, string $role_name): bool
{
    return get_student_plan_tier($user_id, $role_name) >= 2;
}

// ── Tutor booking limit helpers ───────────────────────────────────────────────

/**
 * Returns the effective maximum number of *simultaneous active bookings*
 * a tutor is allowed to hold (pending + accepted combined).
 *
 * Tutor Starter: 5 active bookings at once.
 * Tutor Pro:     0 = unlimited.
 * No subscription: falls back to the Tutor Starter limit from the DB.
 *
 * Note: this is a concurrent-booking cap, not a monthly count —
 * matching the plan description "Up to 5 active bookings".
 *
 * @param int $tutor_user_id  The tutor's users.id (not tutors.id).
 * @return int  0 = unlimited, >0 = max concurrent active bookings.
 */
function get_tutor_booking_limit(int $tutor_user_id): int
{
    $db = get_db();

    $stmt = $db->prepare("
        SELECT
            CASE WHEN MIN(sp.booking_limit) = 0 THEN 0
                 ELSE MAX(sp.booking_limit)
            END AS effective_limit
        FROM subscriptions s
        JOIN subscription_plans sp ON sp.id = s.plan_id
        WHERE s.user_id    = ?
          AND s.status     = 'active'
          AND s.end_date  >= CURDATE()
          AND sp.user_type = 'Tutor'
    ");
    $stmt->execute([$tutor_user_id]);
    $row = $stmt->fetch();

    if ($row !== false && $row['effective_limit'] !== null) {
        return (int) $row['effective_limit'];
    }

    // No active Tutor subscription — fall back to Tutor Starter limit
    $free_stmt = $db->prepare("
        SELECT booking_limit
        FROM subscription_plans
        WHERE user_type = 'Tutor'
          AND LOWER(plan_name) LIKE '%starter%'
        ORDER BY price ASC
        LIMIT 1
    ");
    $free_stmt->execute();
    $free = $free_stmt->fetch();

    return $free !== false ? (int) $free['booking_limit'] : 5;
}

/**
 * Counts a tutor's currently active bookings (pending + accepted).
 * Used to enforce the Tutor Starter concurrent-booking cap.
 *
 * @param int $tutor_id  The tutors.id (not users.id).
 * @return int
 */
function count_tutor_active_bookings(int $tutor_id): int
{
    $stmt = get_db()->prepare("
        SELECT COUNT(*) FROM bookings
        WHERE tutor_id = ?
          AND status IN ('pending', 'accepted')
    ");
    $stmt->execute([$tutor_id]);
    return (int) $stmt->fetchColumn();
}
