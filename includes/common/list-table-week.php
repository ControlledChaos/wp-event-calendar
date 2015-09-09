<?php

/**
 * Calendar Week List Table
 *
 * @since 0.1.8
 *
 * @package Calendar/ListTables/Week
 *
 * @see WP_Posts_List_Table
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Calendar Week List Table
 *
 * This list table is responsible for showing events in a traditional table,
 * even though it extends the `WP_List_Table` class. Tables & lists & tables.
 *
 * @since 0.1.8
 */
class WP_Event_Calendar_Week_Table extends WP_Event_Calendar_List_Table {

	private $week_start = 0;
	private $week_end = 0;

	/**
	 * The main constructor method
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );

		// Setup the week ranges
		$this->week_start = strtotime( 'last Sunday midnight',   $this->today );
		$this->week_end   = strtotime( 'this Saturday midnight', $this->today );
	}

	/**
	 * Setup the list-table's columns
	 *
	 * @see WP_List_Table::single_row_columns()
	 *
	 * @return array An associative array containing column information
	 */
	public function get_columns() {
		return array(
			'hour'      => sprintf( esc_html__( 'Wk. %s', 'wp-event-calendar' ), date_i18n( 'W', $this->today ) ),
			'sunday'    => date_i18n( 'D, M. j', $this->week_start ),
			'monday'    => date_i18n( 'D, M. j', $this->week_start + ( DAY_IN_SECONDS * 1 ) ),
			'tuesday'   => date_i18n( 'D, M. j', $this->week_start + ( DAY_IN_SECONDS * 2 ) ),
			'wednesday' => date_i18n( 'D, M. j', $this->week_start + ( DAY_IN_SECONDS * 3 ) ),
			'thursday'  => date_i18n( 'D, M. j', $this->week_start + ( DAY_IN_SECONDS * 4 ) ),
			'friday'    => date_i18n( 'D, M. j', $this->week_start + ( DAY_IN_SECONDS * 5 ) ),
			'saturday'  => date_i18n( 'D, M. j', $this->week_end )
		);
	}

	/**
	 * Get a list of CSS classes for the list table table tag.
	 *
	 * @since 3.1.0
	 * @access protected
	 *
	 * @return array List of CSS classes for the table tag.
	 */
	protected function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', 'calendar', 'week', $this->_args['plural'] );
	}

	/**
	 * Prepare the list-table items for display
	 *
	 * @since 0.1.8
	 *
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_orderby()
	 * @uses $this->get_order()
	 */
	public function prepare_items() {

		// Set column headers
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			array()
		);

		// Handle bulk actions
		$this->process_bulk_action();

		// Query for posts for this month only
		$this->query = new WP_Query( $this->filter_month_args() );

		// Max per day
		$max_per_day = $this->get_per_day();

		// Rearrange posts into an array keyed by day of the month
		foreach ( $this->query->posts as $post ) {

			// Get start & end
			$this->_all_day = get_post_meta( $post->ID, 'wp_event_calendar_all_day',       true );
			$this->_start   = get_post_meta( $post->ID, 'wp_event_calendar_date_time',     true );
			$this->_end     = get_post_meta( $post->ID, 'wp_event_calendar_end_date_time', true );

			// Format start
			if ( ! empty( $this->_start ) ) {
				$this->_start = strtotime( $this->_start );
			}

			// Format end
			if ( ! empty( $this->_end ) ) {
				$this->_end = strtotime( $this->_end );
			}

			// Prepare pointer & item
			$this->setup_item( $post, $max_per_day );
		}
	}

	/**
	 * Add a post to the items array, keyed by day
	 *
	 * @todo Repeat & expire
	 *
	 * @since 0.1.1
	 *
	 * @param  object  $post
	 * @param  int     $max
	 */
	protected function setup_item( $post = false, $max = 10 ) {

		// Calculate start day
		if ( ! empty( $this->_start ) ) {
			$start_day  = date_i18n( 'j', $this->_start );
			$start_hour = date_i18n( 'H', $this->_start );
		} else {
			$start_day  = 0;
			$start_hour = 0;
		}

		// Calculate end day
		if ( ! empty( $this->_end ) ) {
			$end_day  = date_i18n( 'j', $this->_end );
			$end_hour = date_i18n( 'H', $this->_end );
		} else {
			$end_day  = $start_day;
			$end_hour = $start_hour;
		}

		// Skip overnights for now
		if ( $end_day > $start_day ) {
			return;
		}

		// Start the days loop with the start day
		$hour = $start_hour;

		// Loop through days
		while ( $hour <= $end_hour ) {

			// Setup the pointer for each day
			//$this->setup_pointer( $post, $hour );

			// Add post to items for each day in it's duration
			if ( empty( $this->items[ $hour ][ $start_day ] ) || ( $max > count( $this->items[ $hour ][ $start_day ] ) ) ) {
				$this->items[ $hour ][ $start_day ][ $post->ID ] = $post;
			}

			// Bump the hour
			++$hour;
		}
	}

	/**
	 * Return filtered query arguments
	 *
	 * @since 0.1.1
	 *
	 * @return array
	 */
	private function filter_month_args() {

		// Events
		if ( 'event' === $this->screen->post_type ) {

			// Get boundaries
			$week_start = date_i18n( 'Y-m-d H:i:s', $this->week_start );
			$week_end   = date_i18n( 'Y-m-d H:i:s', $this->week_end   );

			// Setup args
			$args = array(
				'post_type'           => $this->screen->post_type,
				'post_status'         => $this->get_post_status(),
				'posts_per_page'      => -1,
				'orderby'             => 'meta_value',
				'order'               => $this->get_order(),
				'hierarchical'        => false,
				'ignore_sticky_posts' => true,
				's'                   => $this->get_search(),
				'meta_query'          => array(
					array(
						'key'     => 'wp_event_calendar_date_time',
						'value'   => array( $week_start, $week_end ),
						'type'    => 'DATETIME',
						'compare' => 'BETWEEN',
					),

					// Skip all day events in the loop
					array(
						'key'     => 'wp_event_calendar_all_day',
						'compare' => 'NOT EXISTS'
					)
				)
			);

		// All others
		} else {
			$args = array(
				'post_type'           => $this->screen->post_type,
				'post_status'         => $this->get_post_status(),
				'monthnum'            => $this->month,
				'year'                => $this->year,
				'day'                 => null,
				'posts_per_page'      => -1,
				'orderby'             => $this->get_orderby(),
				'order'               => $this->get_order(),
				'hierarchical'        => false,
				'ignore_sticky_posts' => true,
				's'                   => $this->get_search()
			);
		}

		return apply_filters( 'wp_event_calendar_month_query', $args );
	}

	/**
	 * Paginate through months & years
	 *
	 * @since 0.1.8
	 *
	 * @param array $args
	 */
	protected function pagination( $args = array() ) {

		// Parse args
		$args = wp_parse_args( $args, array(
			'small'  => '1 week',
			'large'  => '1 month',
			'labels' => array(
				'next_small' => esc_html__( 'Next Week',      'wp-event-calendar' ),
				'next_large' => esc_html__( 'Next Month',     'wp-event-calendar' ),
				'prev_small' => esc_html__( 'Previous Week',  'wp-event-calendar' ),
				'prev_large' => esc_html__( 'Previous Month', 'wp-event-calendar' )
			)
		) );

		// Return pagination
		return parent::pagination( $args );
	}

	/**
	 * Output month & year inputs, for viewing relevant posts
	 *
	 * @since 0.1.8
	 *
	 * @param  string  $which
	 */
	protected function extra_tablenav( $which = '' ) {

		// No bottom extras
		if ( 'top' !== $which ) {
			return;
		}

		// Start an output buffer
		ob_start(); ?>

		<label for="month" class="screen-reader-text"><?php esc_html_e( 'Switch to this month', 'wp-event-calendar' ); ?></label>
		<select name="month" id="month">

			<?php for ( $month_index = 1; $month_index <= 12; $month_index++ ) : ?>

				<option value="<?php echo esc_attr( $month_index ); ?>" <?php selected( $month_index, $this->month ); ?>><?php echo $GLOBALS['wp_locale']->get_month( $month_index ); ?></option>

			<?php endfor;?>

		</select>

		<label for="year" class="screen-reader-text"><?php esc_html_e( 'Switch to this year', 'wp-event-calendar' ); ?></label>
		<input type="number" name="year" id="year" value="<?php echo (int) $this->year; ?>" size="5">

		<?php

		// Allow additional tablenav output before the "View" button
		do_action( 'wp_event_calendar_before_tablenav_view' );

		// Output the "View" button
		submit_button( esc_html__( 'View', 'wp-event-calendar' ), 'action', '', false, array( 'id' => "doaction" ) );

		// Filter & return
		return apply_filters( 'wp_event_calendar_get_extra_tablenav', ob_get_clean() );
	}

	/**
	 * Start the week with a table row, and a th to show the time
	 *
	 * @since 0.1.8
	 */
	protected function get_all_day_row() {

		// Start an output buffer
		ob_start(); ?>

		<tr class="all-day">
			<th><?php esc_html_e( 'All day', 'wp-event-calendar' ); ?></th>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
		</tr>

		<?php

		// Return the output buffer
		return ob_get_clean();
	}

	/**
	 * Start the week with a table row, and a th to show the time
	 *
	 * @since 0.1.8
	 */
	protected function get_hour_start( $time = 0 ) {

		// No row classes
		$class = '';

		// Is this this hour?
		if ( date_i18n( 'H' ) === date_i18n( 'H', $time ) ) {
			$class = 'class="this-hour"';
		}

		// Start an output buffer
		ob_start(); ?>

		<tr <?php echo $class; ?>><th><?php echo date_i18n( 'g:i a', $time ); ?></th>

		<?php

		// Return the output buffer
		return ob_get_clean();
	}

	/**
	 * End the week with a closed table row
	 *
	 * @since 0.1.8
	 */
	protected function get_hour_end() {

		// Start an output buffer
		ob_start(); ?>

			</tr>

		<?php

		// Return the output buffer
		return ob_get_clean();
	}

	/**
	 * Start the week with a table row
	 *
	 * @since 0.1.8
	 */
	protected function get_hour_for_week( $time = 0 ) {

		// Get week start day
		$week_start = date_i18n( 'j', $this->week_start );
		$hour       = date_i18n( 'H', $time );

		// Start an output buffer
		ob_start();

		// Calculate the day of the month
		for ( $dow = 0; $dow <= 6; $dow++ ) :
			$day = ( $dow + $week_start ); ?>

			<td class="<?php echo $this->get_day_classes( $dow, $day ); ?>">
				<div class="events-for-day">
					<?php echo $this->get_hour_posts_for_day( $hour, $day ); ?>
				</div>
			</td>

		<?php endfor;

		// Return the output buffer
		return ob_get_clean();
	}

	/**
	 * Get the already queried posts for a given day
	 *
	 * @since 0.1.8
	 *
	 * @param int $day
	 *
	 * @return array
	 */
	protected function get_day_queried_posts( $hour = 0, $day = 1 ) {
		return isset( $this->items[ $hour ][ $day ] )
			? $this->items[ $hour ][ $day ]
			: array();
	}

	/**
	 * Get posts for the day
	 *
	 * @since 0.1.8
	 *
	 * @param int $day
	 *
	 * @return string
	 */
	protected function get_hour_posts_for_day( $hour = 0, $day = 1 ) {

		// Get posts and bail if none
		$posts = $this->get_day_queried_posts( $hour, $day );
		if ( empty( $posts ) ) {
			return '';
		}

		// Start an output buffer
		ob_start();

		// Loop through today's posts
		foreach ( $posts as $post ) :

			// Setup the pointer ID
			$ponter_id = "{$post->ID}-{$day}";

			// Get the post link
			$post_link = get_edit_post_link( $post->ID );

			// Handle empty titles
			$post_title = get_the_title( $post->ID );
			if ( empty( $post_title ) ) {
				$post_title = esc_html__( '(No title)', 'wp-event-calendar' );
			} ?>

			<a id="event-pointer-<?php echo esc_attr( $ponter_id ); ?>" href="<?php echo esc_url( $post_link ); ?>" class="<?php echo $this->get_day_post_classes( $post->ID ); ?>"><?php echo esc_html( $post_title ); ?></a>

		<?php endforeach;

		return ob_get_clean();
	}

	/**
	 * Get classes for post in day
	 *
	 * @since 0.1.8
	 *
	 * @param int $post_id
	 */
	protected function get_day_post_classes( $post_id = 0 ) {
		return join( ' ', get_post_class( '', $post_id ) );
	}

	/**
	 * Is the current calendar view today
	 *
	 * @since 0.1.8
	 *
	 * @return bool
	 */
	protected function is_today( $month, $day, $year ) {
		$_month = (bool) ( $month == date_i18n( 'n' ) );
		$_day   = (bool) ( $day   == date_i18n( 'j' ) );
		$_year  = (bool) ( $year  == date_i18n( 'Y' ) );

		return (bool) ( true === $_month && true === $_day && true === $_year );
	}

	/**
	 * Get classes for table cell
	 *
	 * @since 0.1.8
	 *
	 * @param   type  $iterator
	 * @param   type  $start_day
	 *
	 * @return  type
	 */
	protected function get_day_classes( $iterator = 1, $start_day = 1 ) {
		$dow      = ( $iterator % 7 );
		$day_key  = sanitize_key( $GLOBALS['wp_locale']->get_weekday( $dow ) );

		// Position & day info
		$position     = "position-{$dow}";
		$day_number   = "day-{$start_day}";
		$month_number = "month-{$this->month}";
		$year_number  = "year-{$this->year}";

		$is_today = $this->is_today( $this->month, $start_day, $this->year )
			? 'today'
			: '';

		// Assemble classes
		$classes = array(
			$day_key,
			$is_today,
			$position,
			$day_number,
			$month_number,
			$year_number
		);

		return implode( ' ', $classes );
	}

	/**
	 * Display a calendar by month and year
	 *
	 * @since 0.1.8
	 *
	 * @param int $year
	 * @param int $month
	 * @param int $day
	 */
	protected function display_mode( $year = 2015, $month = 1, $day = 1 ) {

		// All day events
		echo $this->get_all_day_row( $year, $month, $day );

		// Loop through days of the month
		for ( $i = 0; $i <= 23; $i++ ) {

			// Get timestamp & hour
			$timestamp = mktime( $i, 0, 0, $month, $day, $year );

			// New row
			echo $this->get_hour_start( $timestamp );

			// Get table cells for all days this week in this hour
			echo $this->get_hour_for_week( $timestamp );

			// Close row
			echo $this->get_hour_end();
		}
	}
}
