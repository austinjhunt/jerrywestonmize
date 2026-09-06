<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace Elementor;

use AmeliaBooking\Infrastructure\WP\GutenbergBlock\GutenbergBlock;
use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;

/**
 * Class AmeliaEventsListBookingElementorWidget
 *
 * @package AmeliaBooking\Infrastructure\WP\Elementor
 */
class AmeliaEventsListBookingElementorWidget extends ElementorSharedShortcodeWidget
{
    public function get_name()
    {
        return 'ameliaeventslistbooking';
    }

    public function get_title()
    {
        return AmeliaElementorWhiteLabelHelper::label(BackendStrings::get('events_list_booking_gutenberg_block')['title']);
    }

    public function get_icon()
    {
        return AmeliaElementorWhiteLabelHelper::icon();
    }

    public function get_categories()
    {
        return [ 'amelia-elementor' ];
    }
    protected function register_controls()
    {
        $controls_data = [];

        ElementorSharedShortcodeWidget::setSharedShortcodeData(GutenbergBlock::getEntitiesData()['data'], $controls_data);

        $this->start_controls_section(
            'amelia_events_section',
            [
                'label' => '<div class="amelia-elementor-content"><p class="amelia-elementor-content-title">'
                    . AmeliaElementorWhiteLabelHelper::label(BackendStrings::get('events_list_booking_gutenberg_block')['title'])
                    . '</p><br><p class="amelia-elementor-content-p">'
                    . AmeliaElementorWhiteLabelHelper::label(BackendStrings::get('events_list_booking_gutenberg_block')['description'])
                    . '</p>',
            ]
        );

        $this->add_control(
            'preselect',
            [
                'label' => BackendStrings::get('filter'),
                'type' => Controls_Manager::SWITCHER,
                'default' => false,
                'label_on' => BackendStrings::get('yes'),
                'label_off' => BackendStrings::get('no'),
            ]
        );

        $this->add_control(
            'event_to_show',
            [
                'label'     => BackendStrings::get('event_time_scope'),
                'type'      => Controls_Manager::SELECT,
                'condition' => ['preselect' => 'yes'],
                'default'   => 'all',
                'options'   => [
                    'all'    => BackendStrings::get('all_events'),
                    'future' => BackendStrings::get('future_events'),
                    'past'   => BackendStrings::get('past_events'),
                    'custom' => BackendStrings::get('custom_range'),
                ],
            ]
        );

        $this->add_control(
            'start_date',
            [
                'label' => BackendStrings::get('red_start_date'),
                'type' => Controls_Manager::DATE_TIME,
                'picker_options' => [
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                ],
                'condition' => [
                    'preselect' => 'yes',
                    'event_to_show' => 'custom'
                ],
            ]
        );

        $this->add_control(
            'end_date',
            [
                'label' => BackendStrings::get('red_end_date'),
                'type' => Controls_Manager::DATE_TIME,
                'picker_options' => [
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                ],
                'condition' => [
                    'preselect' => 'yes',
                    'event_to_show' => 'custom'
                ],
            ]
        );

        $this->add_control(
            'select_event',
            [
                'label' => BackendStrings::get('select_event'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => self::amelia_elementor_get_events(),
                'condition' => ['preselect' => 'yes'],
                'placeholder' => BackendStrings::get('show_all_events')
            ]
        );

        $this->add_control(
            'select_tag',
            [
                'label' => BackendStrings::get('select_tag'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => self::amelia_elementor_get_tags(),
                'condition' => ['preselect' => 'yes'],
                'placeholder' => BackendStrings::get('show_all_tags')
            ]
        );


        $this->add_control(
            'select_location',
            [
                'label' => BackendStrings::get('select_location'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => self::amelia_elementor_get_locations(),
                'condition' => ['preselect' => 'yes'],
                'placeholder' => BackendStrings::get('show_all_locations')
            ]
        );

        $this->add_control(
            'show_recurring',
            [
                'label' => __('Show recurring events:'),
                'type' => Controls_Manager::SWITCHER,
                'condition' => ['preselect' => 'yes'],
                'default' => false,
                'label_on' => BackendStrings::get('yes'),
                'label_off' => BackendStrings::get('no'),
            ]
        );

        $this->setSharedShortcodeElements($controls_data);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        if (Plugin::$instance->editor->is_edit_mode()) {
            $alt = esc_attr(BackendStrings::get('events_list_booking_gutenberg_block')['title']);
            printf(
                '<picture>' .
                '<source media="(max-width: 360px)" srcset="%s" />' .
                '<img class="amelia-elementor-preview" src="%s" alt="%s" style="max-width:100%%;height:auto;display:block;margin:0 auto;" />' .
                '</picture>',
                esc_url(AMELIA_URL . 'public/img/shortcode/elf-mobile-preview.svg'),
                esc_url(AMELIA_URL . 'public/img/shortcode/elf-preview.svg'),
                $alt
            );
            return;
        }

        $settings = $this->get_settings_for_display();

        $sharedShortcode = $this->getSharedShortcodeString($settings);

        if ($settings['preselect']) {
            $selected_event = empty($settings['select_event']) ? '' : ' event="' . (is_array($settings['select_event']) ?
                    implode(',', array_map('esc_attr', $settings['select_event'])) : esc_attr($settings['select_event'])) . '"';

            $event_to_show = '';
            if (empty($settings['select_event']) && !empty($settings['event_to_show']) && $settings['event_to_show'] !== 'all') {
                if ($settings['event_to_show'] === 'custom') {
                    if (empty($settings['start_date']) || empty($settings['end_date'])) {
                        echo BackendStrings::get('notice_for_missing_dates');
                        return;
                    }

                    $event_to_show = ' range="' . esc_attr($settings['start_date'] . ' - ' . $settings['end_date']) . '"';
                } else {
                    $event_to_show = ' range="' . esc_attr($settings['event_to_show']) . '"';
                }
            }

            $show_recurring = $settings['show_recurring'] ? ' recurring=1' : '';

            $selected_location = empty($settings['select_location']) ? '' : ' location="' . (is_array($settings['select_location']) ?
                    implode(',', array_map('esc_attr', $settings['select_location'])) : esc_attr($settings['select_location'])) . '"';

            $selected_tag = '';
            if (!empty($settings['select_tag'])) {
                $selected_tag .= ' tag="';
                if (is_array($settings['select_tag'])) {
                    $tags = array_values(array_filter($settings['select_tag']));
                    foreach ($tags as $index => $tag) {
                        $selected_tag .= ($index === 0 ? '' : ',') . '{' . esc_attr($tag) . '}';
                    }
                } else {
                    $selected_tag .= esc_attr($settings['select_tag']);
                }
                $selected_tag .= '"';
            }

            echo do_shortcode('[' . AmeliaElementorWhiteLabelHelper::shortcodeTag('eventslistbooking', 'ameliaeventslistbooking') .
                $sharedShortcode .
                $selected_event .
                $event_to_show .
                $selected_location .
                $selected_tag .
                $show_recurring . ']');
        } else {
            echo do_shortcode('[' . AmeliaElementorWhiteLabelHelper::shortcodeTag('eventslistbooking', 'ameliaeventslistbooking') .
                $sharedShortcode .
                ']');
        }
    }


    public static function amelia_elementor_get_events()
    {
        $events = GutenbergBlock::getEntitiesData()['data']['events'];

        $returnEvents = [];

        $returnEvents['0'] = BackendStrings::get('show_all_events');

        foreach ($events as $event) {
            $returnEvents[$event['id']] = $event['name'] . ' (id: ' . $event['id'] . ') - ' . $event['formattedPeriodStart'];
        }

        return $returnEvents;
    }

    public static function amelia_elementor_get_locations()
    {
        $locations = GutenbergBlock::getEntitiesData()['data']['locations'];

        $returnLocations = [];

        $returnLocations['0'] = BackendStrings::get('show_all_locations');

        foreach ($locations as $location) {
            $returnLocations[$location['id']] = $location['name'];
        }

        return $returnLocations;
    }

    public static function amelia_elementor_get_tags()
    {
        $tags = GutenbergBlock::getEntitiesData()['data']['tags'];

        $returnTags = [];

        foreach ($tags as $index => $tag) {
            $returnTags[$tag['name']] = $tag['name'];
        }

        return $returnTags;
    }
}
