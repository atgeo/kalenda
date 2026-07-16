<?php
// phpcs:ignoreFile

declare( strict_types=1 );

use function Kalenda\kalenda;

use Kalenda\Api\CalendarQuery;

$date = current_datetime();

$repository  = kalenda()->calendar_repository();
$day_service = kalenda()->day_service();

$query = CalendarQuery::create(
        (string) ( $attributes['type'] ?? 'general' ),
        (string) ( $attributes['calendarId'] ?? '' ),
        (int) $date->format( 'Y' ),
        CalendarQuery::YEAR_CIVIL,
        (string) ( $attributes['locale'] ?? 'en' )
);

$data = $repository->fetch( $query );

if ( $data instanceof WP_Error ) : ?>
    <p class="kalenda-day__error">
        <?php esc_html_e( 'Unable to load today\'s celebrations.', 'kalenda' ); ?>
    </p>
    <?php
    return;
endif;

$events = $day_service->filter(
        (array) ( $data['litcal'] ?? array() ),
        $date
);

// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- $date is already a DateTimeImmutable in wp_timezone(); wp_date() with a timestamp still honours the site's configured date_format and locale.
$today_label = wp_date( get_option( 'date_format' ), $date->getTimestamp() );
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns pre-escaped markup. ?>>
    <h2 class="kalenda-day__title">
        <?php
        printf(
        /* translators: %s: today's date, formatted per the site's Settings > General date format. */
                esc_html__( "Today's Celebrations — %s", 'kalenda' ),
                esc_html( $today_label )
        );
        ?>
    </h2>

    <?php if ( empty( $events ) ) : ?>
        <p class="kalenda-day__empty">
            <?php esc_html_e( 'No celebrations found for today.', 'kalenda' ); ?>
        </p>
    <?php else : ?>
        <div class="kalenda-day__events">
            <ul class="kalenda-day__events-list">
            <?php foreach ( $events as $event ) : ?>
                <li class="kalenda-day__event">
                    <h3 class="kalenda-day__name event-color-<?php echo esc_attr ( (string) $event['color'][0]) ?? 'white' ; ?>">
                        <?php echo esc_html( (string) ( $event['name'] ?? '' ) ); ?>


                    <?php if ( ! empty( $event['grade'] ) ) : ?>
                        <span class="kalenda-day__grade">
                            (<?php echo esc_html( (string) $event['grade_lcl'] ); ?>)
                        </span>
                    <?php endif; ?>
                    </h3>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
