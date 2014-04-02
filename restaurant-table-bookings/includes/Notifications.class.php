<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'rtbNotifications' ) ) {
/**
 * Class to process notifications for Restaurant Table Bookings
 *
 * This class contains the registered notifications and sends them when the
 * event is triggered.
 *
 * @since 0.0.1
 */
class rtbNotifications {

	/**
	 * Booking object (class rtbBooking)
	 *
	 * @var object
	 * @since 0.0.1
	 */
	public $booking;

	/**
	 * Array of rtbNotification objects
	 *
	 * @var array
	 * @since 0.0.1
	 */
	public $notifications;

	/**
	 * Hooks to execute notifications on
	 *
	 * Post Status Transitions and other hooks.
	 * See: http://codex.wordpress.org/Post_Status_Transitions
	 *
	 * @var array
	 * @since 0.0.1
	 */
	public $hooks;

	/**
	 * Set up notifications
	 * @since 0.0.1
	 */
	public function __construct() {

		// Hook into all status changes that require notifications
		$hooks = array(
			'rtb_insert_booking'	=> array( $this, 'new_submission' ), 		// Booking submitted
			'pending_to_confirmed'	=> array( $this, 'pending_to_confirmed' ), 	// Booking confirmed
			'pending_to_closed'		=> array( $this, 'pending_to_closed' ), 	// Booking can not be made
			'confirmed_to_closed'	=> array( $this, 'confirmed_to_closed' ), 	// If booking in past, send a "how'd you like your meal" email.
		);

		$hooks = apply_filters( 'rtb_notification_transition_callbacks', $hooks );

		foreach ( $hooks as $hook => $callback ) {
			add_action( $hook, $callback );
		}

		// Define notifications
		require_once( RTB_PLUGIN_DIR . '/includes/Notification.class.php' );
		require_once( RTB_PLUGIN_DIR . '/includes/Notification.Email.class.php' );
		$this->notifications = array(
			new rtbNotificationEmail( 'new_submission', 'user' ),
			new rtbNotificationEmail( 'new_submission', 'admin' ),
			new rtbNotificationEmail( 'pending_to_confirmed', 'user' ),
			new rtbNotificationEmail( 'pending_to_closed', 'user' ),
			new rtbNotificationEmail( 'confirmed_to_closed', 'user' ),
		);

		$this->notifications = apply_filters( 'rtb_notifications', $this->notifications );
	}

	/**
	 * Set booking data
	 * @since 0.0.1
	 */
	public function set_booking( $booking_post ) {
		require_once( RTB_PLUGIN_DIR . '/includes/Booking.class.php' );
		$this->booking = new rtbBooking();
		$this->booking->load_wp_post( $booking_post );
	}

	/**
	 * New booking submissions
	 *
	 * @var object $booking
	 * @since 0.0.1
	 */
	public function new_submission( $booking ) {

		// Bail early if $booking is not a rtbBooking object
		if ( get_class( $booking ) != 'rtbBooking' ) {
			return;
		}

		$this->booking = $booking;

		$this->event( 'new_submission' );

	}

	/**
	 * Booking confirmed
	 * @since 0.0.1
	 */
	public function pending_to_confirmed( $booking_post ) {

		if ( $booking_post->post_type != RTB_BOOKING_POST_TYPE ) {
			return;
		}

		$this->set_booking( $booking_post );

		$this->event( 'pending_to_confirmed' );

	}

	/**
	 * Booking can not be made
	 * @since 0.0.1
	 */
	public function pending_to_closed( $booking_post ) {

		if ( $booking_post->post_type != RTB_BOOKING_POST_TYPE ) {
			return;
		}

		$this->set_booking( $booking_post );

		$this->event( 'pending_to_closed' );

	}

	/**
	 * Booking was confirmed and is now completed. Send out an optional
	 * follow-up email.
	 *
	 * @since 0.0.1
	 */
	public function confirmed_to_closed( $booking_post ) {

		if ( $booking_post->post_type != RTB_BOOKING_POST_TYPE ) {
			return;
		}

		$this->set_booking( $booking_post );

		$this->event( 'pending_to_closed' );

	}

	/**
	 * Process notifications for an event
	 * @since 0.0.1
	 */
	public function event( $event ) {

		foreach( $this->notifications as $notification ) {

			if ( $event == $notification->event ) {
				$notification->set_booking( $this->booking );
				if ( $notification->prepare_notification() ) {
					$notification->send_notification();
				}
			}
		}

	}

}
} // endif;
