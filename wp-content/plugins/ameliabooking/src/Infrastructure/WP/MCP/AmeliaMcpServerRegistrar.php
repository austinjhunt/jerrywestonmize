<?php

namespace AmeliaBooking\Infrastructure\WP\MCP;

class AmeliaMcpServerRegistrar
{
    private const SERVER_ID = 'amelia-mcp-server';

    /**
     * Register the Amelia MCP server via the adapter.
     *
     * @param mixed $adapter The adapter instance (WP\MCP\Core\McpAdapter).
     */
    public static function init($adapter): void
    {
        add_filter('mcp_adapter_tools_list', array(static::class, 'filterToolsList'), 10, 2);

        $adapter->create_server(
            self::SERVER_ID,
            'mcp',
            self::SERVER_ID,
            'Amelia Booking System',
            'Amelia Booking System – appointments, events, services and customers.',
            '1.0.0',
            array( 'WP\\MCP\\Transport\\HttpTransport' ),
            null,
            null,
            array(
                'amelia/list-services',
                'amelia/list-employees',
                'amelia/list-customers',
                'amelia/list-events',
                'amelia/list-appointments',
                'amelia/add-service',
                'amelia/add-customer',
                'amelia/create-appointment',
                'amelia/create-event',
                'amelia/book-event',
                'amelia/cancel-booking',
                'amelia/check-availability',
            ),
            array(),
            array(),
            static function () {
                if (is_user_logged_in()) {
                    return true;
                }

                $username = sanitize_text_field($_SERVER['PHP_AUTH_USER'] ?? '');
                $password = sanitize_text_field($_SERVER['PHP_AUTH_PW'] ?? '');

                if (! $username || ! $password) {
                    return false;
                }

                $user = wp_authenticate_application_password(null, $username, $password);

                if ($user instanceof \WP_User) {
                    wp_set_current_user($user->ID, $user->user_login);

                    return true;
                }

                return false;
            }
        );
    }

    /**
     * Hides tools the current user cannot execute from the amelia-mcp-server's tools/list response.
     *
     * Execution is already gated by each tool's own permission_callback; this only stops
     * unprivileged users from enumerating tool names/descriptions/schemas they can't run.
     *
     * @param array                  $tools  Array of Tool DTOs.
     * @param \WP\MCP\Core\McpServer $server The MCP server instance.
     *
     * @return array
     */
    public static function filterToolsList(array $tools, $server): array
    {
        if ($server->get_server_id() !== self::SERVER_ID) {
            return $tools;
        }

        return array_values(array_filter($tools, static function ($tool) use ($server) {
            $mcpTool = $server->get_mcp_tool($tool->getName());

            if (!$mcpTool) {
                return true;
            }

            return true === $mcpTool->check_permission(array());
        }));
    }
}
