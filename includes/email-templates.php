<?php
/**
 * AralPal – HTML Email Templates
 *
 * All functions return a full HTML email string with table-based layout
 * and inline styles (no external CSS, no Font Awesome – safe for email clients).
 */

/**
 * Wraps content in the AralPal branded email shell.
 */
function _email_wrap(string $content): string
{
    return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AralPal</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#333333;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6f9;">
    <tr>
      <td align="center" style="padding:32px 16px;">
        <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;">
          <!-- Header -->
          <tr>
            <td style="background:#2563eb;padding:24px 32px;">
              <span style="color:#ffffff;font-size:22px;font-weight:bold;letter-spacing:1px;">AralPal</span>
            </td>
          </tr>
          <!-- Body -->
          <tr>
            <td style="padding:32px;">
              ' . $content . '
            </td>
          </tr>
          <!-- Footer -->
          <tr>
            <td style="background:#f4f6f9;padding:16px 32px;text-align:center;font-size:12px;color:#888888;">
              &copy; ' . date('Y') . ' AralPal. You received this email because you have an account on AralPal.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}

/**
 * Welcome email sent after user registration.
 *
 * @param string $full_name The new user's full name
 */
function email_welcome(string $full_name): string
{
    $name     = htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8');
    $login_url = htmlspecialchars(SITE_URL . '/auth/login.php', ENT_QUOTES, 'UTF-8');

    $content = '
      <h2 style="margin:0 0 16px;color:#2563eb;">Welcome to AralPal, ' . $name . '!</h2>
      <p style="margin:0 0 12px;">
        Your account has been created. AralPal connects students with expert tutors across a wide
        range of subjects — so you can start learning on your own schedule.
      </p>
      <table cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;">
        <tr>
          <td style="background:#2563eb;border-radius:6px;">
            <a href="' . $login_url . '" style="display:inline-block;padding:12px 28px;color:#ffffff;font-weight:bold;text-decoration:none;font-size:15px;">Sign in to AralPal</a>
          </td>
        </tr>
      </table>
      <p style="margin:0 0 12px;color:#555555;font-size:13px;">
        If you did not create this account, you can safely ignore this email.
      </p>
      <p style="margin:0;">&#8212; The AralPal Team</p>
    ';

    return _email_wrap($content);
}

/**
 * Booking request confirmation sent to the student after they submit a booking.
 *
 * @param array  $booking    Booking row (must include session_date, start_time, end_time, subject_name)
 * @param string $tutor_name Tutor's full name
 */
function email_booking_confirmed_student(array $booking, string $tutor_name): string
{
    $student_name  = htmlspecialchars($booking['student_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $tutor         = htmlspecialchars($tutor_name, ENT_QUOTES, 'UTF-8');
    $subject       = htmlspecialchars($booking['subject_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $date          = htmlspecialchars(date('F j, Y', strtotime($booking['session_date'])), ENT_QUOTES, 'UTF-8');
    $time          = htmlspecialchars(date('g:i A', strtotime($booking['start_time'])), ENT_QUOTES, 'UTF-8')
                   . ' &#8211; '
                   . htmlspecialchars(date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8');
    $bookings_url  = htmlspecialchars(SITE_URL . '/booking/my-bookings.php', ENT_QUOTES, 'UTF-8');

    $content = '
      <p style="margin:0 0 16px;">Hi ' . $student_name . ',</p>
      <p style="margin:0 0 16px;">
        Your booking request has been sent to <strong>' . $tutor . '</strong>. Here are the details:
      </p>
      <table cellpadding="8" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:0 0 24px;">
        <tr style="background:#f4f6f9;">
          <td style="padding:10px 14px;font-weight:bold;width:120px;">Subject</td>
          <td style="padding:10px 14px;">' . $subject . '</td>
        </tr>
        <tr>
          <td style="padding:10px 14px;font-weight:bold;">Tutor</td>
          <td style="padding:10px 14px;">' . $tutor . '</td>
        </tr>
        <tr style="background:#f4f6f9;">
          <td style="padding:10px 14px;font-weight:bold;">Date</td>
          <td style="padding:10px 14px;">' . $date . '</td>
        </tr>
        <tr>
          <td style="padding:10px 14px;font-weight:bold;">Time</td>
          <td style="padding:10px 14px;">' . $time . '</td>
        </tr>
        <tr style="background:#f4f6f9;">
          <td style="padding:10px 14px;font-weight:bold;">Status</td>
          <td style="padding:10px 14px;"><span style="color:#d97706;font-weight:bold;">Pending</span></td>
        </tr>
      </table>
      <p style="margin:0 0 16px;">
        The tutor will review your request and accept or decline it shortly.
        You can track your booking in My Bookings.
      </p>
      <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
          <td style="background:#2563eb;border-radius:6px;">
            <a href="' . $bookings_url . '" style="display:inline-block;padding:12px 28px;color:#ffffff;font-weight:bold;text-decoration:none;font-size:15px;">View My Bookings</a>
          </td>
        </tr>
      </table>
      <p style="margin:0;">&#8212; The AralPal Team</p>
    ';

    return _email_wrap($content);
}

/**
 * New booking request notification sent to the tutor.
 *
 * @param array  $booking      Booking row
 * @param string $student_name Student's full name
 */
function email_booking_confirmed_tutor(array $booking, string $student_name): string
{
    $tutor_name   = htmlspecialchars($booking['tutor_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $student      = htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');
    $subject      = htmlspecialchars($booking['subject_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $date         = htmlspecialchars(date('F j, Y', strtotime($booking['session_date'])), ENT_QUOTES, 'UTF-8');
    $time         = htmlspecialchars(date('g:i A', strtotime($booking['start_time'])), ENT_QUOTES, 'UTF-8')
                  . ' &#8211; '
                  . htmlspecialchars(date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8');
    $bookings_url = htmlspecialchars(SITE_URL . '/booking/my-bookings.php', ENT_QUOTES, 'UTF-8');

    $content = '
      <p style="margin:0 0 16px;">Hi ' . $tutor_name . ',</p>
      <p style="margin:0 0 16px;">
        You have a new booking request from <strong>' . $student . '</strong>:
      </p>
      <table cellpadding="8" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:0 0 24px;">
        <tr style="background:#f4f6f9;">
          <td style="padding:10px 14px;font-weight:bold;width:120px;">Student</td>
          <td style="padding:10px 14px;">' . $student . '</td>
        </tr>
        <tr>
          <td style="padding:10px 14px;font-weight:bold;">Subject</td>
          <td style="padding:10px 14px;">' . $subject . '</td>
        </tr>
        <tr style="background:#f4f6f9;">
          <td style="padding:10px 14px;font-weight:bold;">Date</td>
          <td style="padding:10px 14px;">' . $date . '</td>
        </tr>
        <tr>
          <td style="padding:10px 14px;font-weight:bold;">Time</td>
          <td style="padding:10px 14px;">' . $time . '</td>
        </tr>
      </table>
      <p style="margin:0 0 16px;">
        Log in to accept or decline this request.
      </p>
      <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
          <td style="background:#2563eb;border-radius:6px;">
            <a href="' . $bookings_url . '" style="display:inline-block;padding:12px 28px;color:#ffffff;font-weight:bold;text-decoration:none;font-size:15px;">Accept or Decline</a>
          </td>
        </tr>
      </table>
      <p style="margin:0;">&#8212; The AralPal Team</p>
    ';

    return _email_wrap($content);
}

/**
 * Status change notification sent to the student when a tutor accepts, declines,
 * or completes a booking. Also used for the tutor's own accept confirmation when
 * called with the tutor's perspective.
 *
 * @param array  $booking    Booking row (session_date, start_time, end_time, subject_name)
 * @param string $new_status 'accepted', 'declined', or 'completed'
 * @param string $tutor_name Tutor's full name
 * @param string $review_url Optional URL to leave a review (used when status = 'completed')
 */
function email_booking_status_changed(array $booking, string $new_status, string $tutor_name, string $review_url = ''): string
{
    $student_name = htmlspecialchars($booking['student_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $tutor        = htmlspecialchars($tutor_name, ENT_QUOTES, 'UTF-8');
    $subject      = htmlspecialchars($booking['subject_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $date         = htmlspecialchars(date('F j, Y', strtotime($booking['session_date'])), ENT_QUOTES, 'UTF-8');
    $time         = htmlspecialchars(date('g:i A', strtotime($booking['start_time'])), ENT_QUOTES, 'UTF-8')
                  . ' &#8211; '
                  . htmlspecialchars(date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8');

    $status_map = [
        'accepted'  => ['label' => 'Accepted',  'color' => '#16a34a'],
        'declined'  => ['label' => 'Declined',  'color' => '#dc2626'],
        'completed' => ['label' => 'Completed', 'color' => '#2563eb'],
    ];
    $status_info   = $status_map[$new_status] ?? ['label' => ucfirst($new_status), 'color' => '#555555'];
    $status_label  = htmlspecialchars($status_info['label'], ENT_QUOTES, 'UTF-8');
    $status_color  = $status_info['color'];

    $messages = [
        'accepted'  => 'Great news — your booking with <strong>' . $tutor . '</strong> for <strong>' . $subject . '</strong> on <strong>' . $date . '</strong> at <strong>' . $time . '</strong> has been <span style="color:' . $status_color . ';font-weight:bold;">accepted</span>.',
        'declined'  => 'Unfortunately, your booking with <strong>' . $tutor . '</strong> for <strong>' . $subject . '</strong> on <strong>' . $date . '</strong> at <strong>' . $time . '</strong> has been <span style="color:' . $status_color . ';font-weight:bold;">declined</span>. Please try booking another time slot.',
        'completed' => 'Your session with <strong>' . $tutor . '</strong> for <strong>' . $subject . '</strong> on <strong>' . $date . '</strong> has been marked <span style="color:' . $status_color . ';font-weight:bold;">complete</span>. We hope it went well!',
    ];

    $message_text = $messages[$new_status] ?? 'Your booking status has been updated to <strong>' . $status_label . '</strong>.';

    $review_section = '';
    if ($new_status === 'completed' && !empty($review_url)) {
        $review_href = htmlspecialchars($review_url, ENT_QUOTES, 'UTF-8');
        $review_section = '
      <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
          <td style="background:#2563eb;border-radius:6px;">
            <a href="' . $review_href . '" style="display:inline-block;padding:12px 28px;color:#ffffff;font-weight:bold;text-decoration:none;font-size:15px;">Leave a Review</a>
          </td>
        </tr>
      </table>';
    }

    $content = '
      <p style="margin:0 0 16px;">Hi ' . $student_name . ',</p>
      <p style="margin:0 0 16px;">' . $message_text . '</p>
      <table cellpadding="8" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:0 0 24px;">
        <tr style="background:#f4f6f9;">
          <td style="padding:10px 14px;font-weight:bold;width:120px;">Subject</td>
          <td style="padding:10px 14px;">' . $subject . '</td>
        </tr>
        <tr>
          <td style="padding:10px 14px;font-weight:bold;">Tutor</td>
          <td style="padding:10px 14px;">' . $tutor . '</td>
        </tr>
        <tr style="background:#f4f6f9;">
          <td style="padding:10px 14px;font-weight:bold;">Date</td>
          <td style="padding:10px 14px;">' . $date . '</td>
        </tr>
        <tr>
          <td style="padding:10px 14px;font-weight:bold;">Time</td>
          <td style="padding:10px 14px;">' . $time . '</td>
        </tr>
        <tr style="background:#f4f6f9;">
          <td style="padding:10px 14px;font-weight:bold;">Status</td>
          <td style="padding:10px 14px;"><span style="color:' . $status_color . ';font-weight:bold;">' . $status_label . '</span></td>
        </tr>
      </table>
      ' . $review_section . '
      <p style="margin:0;">&#8212; The AralPal Team</p>
    ';

    return _email_wrap($content);
}

/**
 * Tutor-side accept confirmation: sent to the tutor when they accept a booking.
 *
 * @param array  $booking      Booking row
 * @param string $student_name Student's full name
 */
function email_tutor_accept_confirmation(array $booking, string $student_name): string
{
    $tutor_name  = htmlspecialchars($booking['tutor_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $student     = htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');
    $subject     = htmlspecialchars($booking['subject_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $date        = htmlspecialchars(date('F j, Y', strtotime($booking['session_date'])), ENT_QUOTES, 'UTF-8');
    $time        = htmlspecialchars(date('g:i A', strtotime($booking['start_time'])), ENT_QUOTES, 'UTF-8')
                 . ' &#8211; '
                 . htmlspecialchars(date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8');
    $bookings_url = htmlspecialchars(SITE_URL . '/booking/my-bookings.php', ENT_QUOTES, 'UTF-8');

    $content = '
      <p style="margin:0 0 16px;">Hi ' . $tutor_name . ',</p>
      <p style="margin:0 0 16px;">
        You have accepted the booking with <strong>' . $student . '</strong>. Here is a summary:
      </p>
      <table cellpadding="8" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:0 0 24px;">
        <tr style="background:#f4f6f9;">
          <td style="padding:10px 14px;font-weight:bold;width:120px;">Student</td>
          <td style="padding:10px 14px;">' . $student . '</td>
        </tr>
        <tr>
          <td style="padding:10px 14px;font-weight:bold;">Subject</td>
          <td style="padding:10px 14px;">' . $subject . '</td>
        </tr>
        <tr style="background:#f4f6f9;">
          <td style="padding:10px 14px;font-weight:bold;">Date</td>
          <td style="padding:10px 14px;">' . $date . '</td>
        </tr>
        <tr>
          <td style="padding:10px 14px;font-weight:bold;">Time</td>
          <td style="padding:10px 14px;">' . $time . '</td>
        </tr>
      </table>
      <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
          <td style="background:#2563eb;border-radius:6px;">
            <a href="' . $bookings_url . '" style="display:inline-block;padding:12px 28px;color:#ffffff;font-weight:bold;text-decoration:none;font-size:15px;">View My Bookings</a>
          </td>
        </tr>
      </table>
      <p style="margin:0;">&#8212; The AralPal Team</p>
    ';

    return _email_wrap($content);
}
