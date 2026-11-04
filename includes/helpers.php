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
