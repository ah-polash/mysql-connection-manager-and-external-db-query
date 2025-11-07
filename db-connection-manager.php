<?php
/**
 * Plugin Name: DB Connection Manager
 * Description: Manage external database connections (MySQL, MongoDB) and query them via shortcode.
 * Version: 1.1.0
 * Author: OpenAI Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DB_Connection_Manager {
    const POST_TYPE = 'db_connection';
    const VERSION   = '1.1.0';
    const META_FIELDS = [
        'db_type',
        'host',
        'port',
        'username',
        'password',
        'database',
        'options',
        'status',
        'status_message',
        'status_updated',
    ];

    /**
     * Whether we have queued an admin notice for the current request.
     *
     * @var bool
     */
    private $notice_queued = false;

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_post' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'admin_notices', [ $this, 'render_admin_notice' ] );
        add_action( 'wp_ajax_dbcm_test_connection', [ $this, 'handle_test_connection' ] );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'register_admin_columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'render_admin_columns' ], 10, 2 );
        add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', [ $this, 'sortable_columns' ] );
        add_shortcode( 'external_db_query', [ $this, 'handle_shortcode' ] );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'db-connection-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function register_post_type() {
        $labels = [
            'name'               => __( 'DB Connections', 'db-connection-manager' ),
            'singular_name'      => __( 'DB Connection', 'db-connection-manager' ),
            'add_new_item'       => __( 'Add New DB Connection', 'db-connection-manager' ),
            'edit_item'          => __( 'Edit DB Connection', 'db-connection-manager' ),
            'new_item'           => __( 'New DB Connection', 'db-connection-manager' ),
            'view_item'          => __( 'View DB Connection', 'db-connection-manager' ),
            'search_items'       => __( 'Search DB Connections', 'db-connection-manager' ),
            'not_found'          => __( 'No DB Connections found', 'db-connection-manager' ),
            'not_found_in_trash' => __( 'No DB Connections found in Trash', 'db-connection-manager' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => false,
            'supports'           => [ 'title' ],
            'menu_icon'          => 'dashicons-database',
            'capability_type'    => 'post',
        ];

        register_post_type( self::POST_TYPE, $args );
    }

    public function register_meta_boxes() {
        add_meta_box(
            'db_connection_details',
            __( 'Connection Details', 'db-connection-manager' ),
            [ $this, 'render_meta_box' ],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'db_connection_save', 'db_connection_nonce' );
        $values = $this->get_meta_values( $post->ID );
        ?>
        <table class="form-table">
            <tbody>
            <tr>
                <th><label for="db_type"><?php esc_html_e( 'Database Type', 'db-connection-manager' ); ?></label></th>
                <td>
                    <select name="db_connection[db_type]" id="db_type">
                        <option value="mysql" <?php selected( $values['db_type'], 'mysql' ); ?>><?php esc_html_e( 'MySQL', 'db-connection-manager' ); ?></option>
                        <option value="mongodb" <?php selected( $values['db_type'], 'mongodb' ); ?>><?php esc_html_e( 'MongoDB', 'db-connection-manager' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="db_host"><?php esc_html_e( 'Host', 'db-connection-manager' ); ?></label></th>
                <td><input type="text" id="db_host" name="db_connection[host]" value="<?php echo esc_attr( $values['host'] ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="db_port"><?php esc_html_e( 'Port', 'db-connection-manager' ); ?></label></th>
                <td><input type="text" id="db_port" name="db_connection[port]" value="<?php echo esc_attr( $values['port'] ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="db_username"><?php esc_html_e( 'Username', 'db-connection-manager' ); ?></label></th>
                <td><input type="text" id="db_username" name="db_connection[username]" value="<?php echo esc_attr( $values['username'] ); ?>" class="regular-text" autocomplete="off" /></td>
            </tr>
            <tr>
                <th><label for="db_password"><?php esc_html_e( 'Password', 'db-connection-manager' ); ?></label></th>
                <td><input type="password" id="db_password" name="db_connection[password]" value="<?php echo esc_attr( $values['password'] ); ?>" class="regular-text" autocomplete="new-password" /></td>
            </tr>
            <tr>
                <th><label for="db_database"><?php esc_html_e( 'Database / Auth Source', 'db-connection-manager' ); ?></label></th>
                <td><input type="text" id="db_database" name="db_connection[database]" value="<?php echo esc_attr( $values['database'] ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="db_options"><?php esc_html_e( 'Additional Options (JSON)', 'db-connection-manager' ); ?></label></th>
                <td><textarea id="db_options" name="db_connection[options]" class="large-text" rows="4"><?php echo esc_textarea( $values['options'] ); ?></textarea></td>
            </tr>
            </tbody>
        </table>
        <p>
            <button type="button" class="button" id="db-connection-test" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                <?php esc_html_e( 'Test Connection', 'db-connection-manager' ); ?>
            </button>
            <span id="db-connection-test-result" style="margin-left:10px;"></span>
        </p>
        <p class="db-connection-status-summary">
            <strong><?php esc_html_e( 'Last Status:', 'db-connection-manager' ); ?></strong>
            <?php
            $status_class = ! empty( $values['status'] ) ? 'status-' . sanitize_html_class( $values['status'] ) : '';
            ?>
            <span id="db-connection-last-status-text" class="db-connection-status-value <?php echo esc_attr( $status_class ); ?>">
                <?php
                if ( ! empty( $values['status'] ) ) {
                    echo esc_html( ucfirst( $values['status'] ) );
                } else {
                    esc_html_e( 'Not checked yet.', 'db-connection-manager' );
                }
                ?>
            </span>
            <span id="db-connection-last-status-message">
                <?php
                if ( ! empty( $values['status_message'] ) ) {
                    echo ' &mdash; ' . esc_html( $values['status_message'] );
                }
                ?>
            </span>
            <br />
            <small id="db-connection-last-status-updated">
                <?php
                if ( ! empty( $values['status_updated'] ) ) {
                    printf(
                        esc_html__( 'Updated: %s', 'db-connection-manager' ),
                        esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), intval( $values['status_updated'] ) ) )
                    );
                }
                ?>
            </small>
        </p>
        <?php
    }

    private function get_meta_values( $post_id ) {
        $values = [];
        foreach ( self::META_FIELDS as $field ) {
            $values[ $field ] = get_post_meta( $post_id, '_db_connection_' . $field, true );
        }

        $values = wp_parse_args( $values, [
            'db_type'         => 'mysql',
            'host'            => '',
            'port'            => '',
            'username'        => '',
            'password'        => '',
            'database'        => '',
            'options'         => '',
            'status'          => '',
            'status_message'  => '',
            'status_updated'  => '',
        ] );

        return $values;
    }

    public function save_post( $post_id ) {
        if ( ! isset( $_POST['db_connection_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['db_connection_nonce'] ) ), 'db_connection_save' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $data       = isset( $_POST['db_connection'] ) ? (array) wp_unslash( $_POST['db_connection'] ) : [];
        $sanitized  = $this->sanitize_connection_data( $data );
        $json_error = null;

        $normalized_options = $this->normalize_options_json( $sanitized['options'], $json_error );

        if ( false === $normalized_options ) {
            $sanitized['options'] = '';
            if ( $json_error ) {
                $this->queue_admin_notice( $json_error );
            }
        } else {
            $sanitized['options'] = $normalized_options;
        }

        foreach ( $sanitized as $key => $value ) {
            update_post_meta( $post_id, '_db_connection_' . $key, $value );
        }
    }

    public function enqueue_admin_assets( $hook ) {
        $screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $post_type = $screen && isset( $screen->post_type ) ? $screen->post_type : ( isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : '' );

        if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && self::POST_TYPE === $post_type ) {
            wp_enqueue_script( 'db-connection-admin', plugins_url( 'assets/js/db-connection-admin.js', __FILE__ ), [ 'jquery' ], self::VERSION, true );
            wp_localize_script( 'db-connection-admin', 'DBConnectionAdmin', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'db_connection_test' ),
                'strings' => [
                    'checking' => __( 'Checking…', 'db-connection-manager' ),
                    'success'  => __( 'Connection established.', 'db-connection-manager' ),
                    'failure'  => __( 'Connection failed.', 'db-connection-manager' ),
                    'notChecked' => __( 'Not checked yet.', 'db-connection-manager' ),
                    'lastChecked' => __( 'Updated: %s', 'db-connection-manager' ),
                    'statuses' => [
                        'success' => __( 'Success', 'db-connection-manager' ),
                        'error'   => __( 'Error', 'db-connection-manager' ),
                    ],
                ],
            ] );
        }

        if ( 'edit.php' === $hook && self::POST_TYPE === $post_type ) {
            wp_enqueue_style( 'db-connection-admin', plugins_url( 'assets/css/db-connection-admin.css', __FILE__ ), [], self::VERSION );
        }
    }

    public function handle_test_connection() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'db-connection-manager' ) ] );
        }

        check_ajax_referer( 'db_connection_test', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $data    = isset( $_POST['credentials'] ) ? (array) wp_unslash( $_POST['credentials'] ) : [];

        if ( empty( $post_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid connection ID.', 'db-connection-manager' ) ] );
        }

        $creds      = $this->sanitize_connection_data( $data );
        $json_error = null;

        $normalized_options = $this->normalize_options_json( $creds['options'], $json_error );

        if ( false === $normalized_options ) {
            wp_send_json_error( [ 'message' => $json_error ] );
        }

        $creds['options'] = $normalized_options;

        $result    = $this->test_connection( $creds );
        $timestamp = time();

        update_post_meta( $post_id, '_db_connection_status', $result['status'] );
        update_post_meta( $post_id, '_db_connection_status_message', $result['message'] );
        update_post_meta( $post_id, '_db_connection_status_updated', $timestamp );

        $result['checked_at'] = $timestamp;

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    private function test_connection( $creds ) {
        $status  = 'error';
        $message = '';

        if ( 'mysql' === $creds['db_type'] ) {
            $mysqli = @mysqli_init();
            if ( ! $mysqli ) {
                $message = __( 'Failed to initialize MySQL connection.', 'db-connection-manager' );
            } else {
                if ( ! empty( $creds['port'] ) ) {
                    $port = intval( $creds['port'] );
                } else {
                    $port = ini_get( 'mysqli.default_port' );
                }

                $connected = @$mysqli->real_connect( $creds['host'], $creds['username'], $creds['password'], $creds['database'], $port );
                if ( $connected ) {
                    $status  = 'success';
                    $message = __( 'MySQL connection established.', 'db-connection-manager' );
                    $mysqli->close();
                } else {
                    $message = sprintf( __( 'MySQL connection failed: %s', 'db-connection-manager' ), $mysqli->connect_error );
                }
            }
        } elseif ( 'mongodb' === $creds['db_type'] ) {
            if ( ! class_exists( '\\MongoDB\\Client' ) ) {
                $message = __( 'MongoDB PHP extension is not installed.', 'db-connection-manager' );
            } else {
                $uri = 'mongodb://';
                if ( ! empty( $creds['username'] ) ) {
                    $uri .= rawurlencode( $creds['username'] );
                    if ( ! empty( $creds['password'] ) ) {
                        $uri .= ':' . rawurlencode( $creds['password'] );
                    }
                    $uri .= '@';
                }
                $uri .= $creds['host'];
                if ( ! empty( $creds['port'] ) ) {
                    $uri .= ':' . intval( $creds['port'] );
                }
                $options = [];
                if ( ! empty( $creds['options'] ) ) {
                    $decoded = json_decode( $creds['options'], true );
                    if ( is_array( $decoded ) ) {
                        $options = $decoded;
                    }
                }
                try {
                    $client = new \MongoDB\Client( $uri, $options );
                    $client->selectDatabase( $creds['database'] )->command( [ 'ping' => 1 ] );
                    $status  = 'success';
                    $message = __( 'MongoDB connection established.', 'db-connection-manager' );
                } catch ( \Exception $e ) {
                    $message = sprintf( __( 'MongoDB connection failed: %s', 'db-connection-manager' ), $e->getMessage() );
                }
            }
        } else {
            $message = __( 'Unsupported database type.', 'db-connection-manager' );
        }

        return [
            'success' => 'success' === $status,
            'status'  => $status,
            'message' => $message,
        ];
    }

    public function register_admin_columns( $columns ) {
        $columns = [
            'cb'        => $columns['cb'],
            'title'     => __( 'Name', 'db-connection-manager' ),
            'db_type'   => __( 'Type', 'db-connection-manager' ),
            'host'      => __( 'Host', 'db-connection-manager' ),
            'port'      => __( 'Port', 'db-connection-manager' ),
            'username'  => __( 'Username', 'db-connection-manager' ),
            'password'  => __( 'Password', 'db-connection-manager' ),
            'database'  => __( 'Database', 'db-connection-manager' ),
            'options'   => __( 'Options', 'db-connection-manager' ),
            'status'    => __( 'Status', 'db-connection-manager' ),
            'updated'   => __( 'Last Checked', 'db-connection-manager' ),
        ];

        return $columns;
    }

    public function render_admin_columns( $column, $post_id ) {
        $values = $this->get_meta_values( $post_id );

        switch ( $column ) {
            case 'db_type':
                echo esc_html( strtoupper( $values['db_type'] ) );
                break;
            case 'host':
            case 'port':
            case 'username':
            case 'database':
                echo esc_html( $values[ $column ] );
                break;
            case 'password':
                if ( ! empty( $values['password'] ) ) {
                    echo esc_html( str_repeat( '•', strlen( $values['password'] ) ) );
                }
                break;
            case 'options':
                if ( ! empty( $values['options'] ) ) {
                    echo '<pre class="db-connection-options">' . esc_html( $values['options'] ) . '</pre>';
                }
                break;
            case 'status':
                if ( ! empty( $values['status'] ) ) {
                    $status_class = 'success' === $values['status'] ? 'status-success' : 'status-error';
                    echo '<span class="' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $values['status'] ) ) . '</span>';
                    if ( ! empty( $values['status_message'] ) ) {
                        echo '<br /><small>' . esc_html( $values['status_message'] ) . '</small>';
                    }
                } else {
                    esc_html_e( 'Not checked yet.', 'db-connection-manager' );
                }
                break;
            case 'updated':
                if ( ! empty( $values['status_updated'] ) ) {
                    echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), intval( $values['status_updated'] ) ) );
                }
                break;
        }
    }

    public function sortable_columns( $columns ) {
        $columns['db_type'] = 'db_type';
        $columns['host']    = 'host';
        $columns['status']  = 'status';

        return $columns;
    }

    public function handle_shortcode( $atts, $content = null ) {
        $atts = shortcode_atts(
            [
                'id'          => 0,
                'query'       => '',
                'collection'  => '',
                'filter'      => '{}',
                'projection'  => '{}',
                'limit'       => 20,
                'template'    => 'table',
            ],
            $atts,
            'external_db_query'
        );

        $post_id = absint( $atts['id'] );
        if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) ) {
            return esc_html__( 'Invalid DB Connection ID.', 'db-connection-manager' );
        }

        if ( 'publish' !== get_post_status( $post_id ) ) {
            return esc_html__( 'The requested connection is not available.', 'db-connection-manager' );
        }

        $creds = $this->get_meta_values( $post_id );

        if ( 'mysql' === $creds['db_type'] ) {
            $query = $atts['query'];
            if ( empty( $query ) && ! empty( $content ) ) {
                $query = $content;
            }

            $template = sanitize_key( $atts['template'] );
            if ( ! in_array( $template, [ 'table', 'json' ], true ) ) {
                $template = 'table';
            }

            return $this->render_mysql_query( $creds, $query, $template, absint( $atts['limit'] ) );
        }

        if ( 'mongodb' === $creds['db_type'] ) {
            return $this->render_mongodb_query( $creds, $atts );
        }

        return esc_html__( 'Unsupported database type.', 'db-connection-manager' );
    }

    private function render_mysql_query( $creds, $query, $template, $limit ) {
        $query = trim( $query );

        if ( empty( $query ) ) {
            return esc_html__( 'No query provided.', 'db-connection-manager' );
        }

        if ( ! preg_match( '/^\s*SELECT/i', $query ) ) {
            return esc_html__( 'Only SELECT queries are allowed.', 'db-connection-manager' );
        }

        if ( $limit > 0 && ! preg_match( '/\bLIMIT\b/i', $query ) ) {
            $query = rtrim( $query, ';' ) . ' LIMIT ' . intval( $limit );
        }

        $mysqli = @mysqli_init();
        if ( ! $mysqli ) {
            return esc_html__( 'Could not initialize MySQL connection.', 'db-connection-manager' );
        }

        $port      = ! empty( $creds['port'] ) ? intval( $creds['port'] ) : ini_get( 'mysqli.default_port' );
        $connected = @$mysqli->real_connect( $creds['host'], $creds['username'], $creds['password'], $creds['database'], $port );

        if ( ! $connected ) {
            return esc_html__( 'Failed to connect to MySQL database.', 'db-connection-manager' );
        }

        $result = @$mysqli->query( $query );

        if ( false === $result ) {
            $mysqli->close();
            return esc_html__( 'Query failed to execute.', 'db-connection-manager' );
        }

        $output = '';

        if ( 'table' === $template ) {
            $output .= '<table class="db-connection-table">';
            $output .= '<thead><tr>';

            $fields = $result->fetch_fields();
            foreach ( $fields as $field ) {
                $output .= '<th>' . esc_html( $field->name ) . '</th>';
            }
            $output .= '</tr></thead><tbody>';

            while ( $row = $result->fetch_assoc() ) {
                $output .= '<tr>';
                foreach ( $row as $value ) {
                    $output .= '<td>' . esc_html( maybe_serialize( $value ) ) . '</td>';
                }
                $output .= '</tr>';
            }
            $output .= '</tbody></table>';
        } else {
            $rows = [];
            while ( $row = $result->fetch_assoc() ) {
                $rows[] = $row;
            }
            $output .= '<pre class="db-connection-pre">' . esc_html( wp_json_encode( $rows, JSON_PRETTY_PRINT ) ) . '</pre>';
        }

        $result->free();
        $mysqli->close();

        return $output;
    }

    private function render_mongodb_query( $creds, $atts ) {
        if ( ! class_exists( '\\MongoDB\\Client' ) ) {
            return esc_html__( 'MongoDB PHP extension is not installed.', 'db-connection-manager' );
        }

        $uri = 'mongodb://';
        if ( ! empty( $creds['username'] ) ) {
            $uri .= rawurlencode( $creds['username'] );
            if ( ! empty( $creds['password'] ) ) {
                $uri .= ':' . rawurlencode( $creds['password'] );
            }
            $uri .= '@';
        }
        $uri .= $creds['host'];
        if ( ! empty( $creds['port'] ) ) {
            $uri .= ':' . intval( $creds['port'] );
        }

        $options = [];
        if ( ! empty( $creds['options'] ) ) {
            $decoded = json_decode( $creds['options'], true );
            if ( is_array( $decoded ) ) {
                $options = $decoded;
            }
        }

        try {
            $client     = new \MongoDB\Client( $uri, $options );
            $collection = trim( $atts['collection'] );
            if ( empty( $collection ) ) {
                return esc_html__( 'No collection provided.', 'db-connection-manager' );
            }

            $filter_error = null;
            $filter       = $this->decode_json_argument( $atts['filter'], $filter_error );
            if ( null === $filter ) {
                return esc_html( $filter_error );
            }

            $projection_error = null;
            $projection       = $this->decode_json_argument( $atts['projection'], $projection_error );
            if ( null === $projection ) {
                return esc_html( $projection_error );
            }

            $limit = absint( $atts['limit'] );

            $cursor = $client->selectCollection( $creds['database'], $collection )->find(
                $filter,
                array_filter(
                    [
                        'projection' => $projection,
                        'limit'      => $limit > 0 ? $limit : null,
                    ]
                )
            );

            $rows   = iterator_to_array( $cursor );
            $output = '<pre class="db-connection-pre">' . esc_html( wp_json_encode( $rows, JSON_PRETTY_PRINT ) ) . '</pre>';

            return $output;
        } catch ( \Exception $e ) {
            return esc_html( $e->getMessage() );
        }
    }

    private function sanitize_connection_data( array $data ) {
        return [
            'db_type'  => isset( $data['db_type'] ) && in_array( $data['db_type'], [ 'mysql', 'mongodb' ], true ) ? $data['db_type'] : 'mysql',
            'host'     => isset( $data['host'] ) ? sanitize_text_field( $data['host'] ) : '',
            'port'     => isset( $data['port'] ) ? preg_replace( '/[^0-9]/', '', (string) $data['port'] ) : '',
            'username' => isset( $data['username'] ) ? sanitize_text_field( $data['username'] ) : '',
            'password' => isset( $data['password'] ) ? (string) $data['password'] : '',
            'database' => isset( $data['database'] ) ? sanitize_text_field( $data['database'] ) : '',
            'options'  => isset( $data['options'] ) ? (string) $data['options'] : '',
        ];
    }

    private function normalize_options_json( $raw, &$error_message = null ) {
        $raw = trim( (string) $raw );

        if ( '' === $raw ) {
            return '';
        }

        $decoded = json_decode( $raw, true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            $error_message = __( 'Additional Options must contain valid JSON.', 'db-connection-manager' );
            return false;
        }

        return wp_json_encode( $decoded );
    }

    private function decode_json_argument( $raw, &$error_message = null ) {
        $raw = trim( (string) $raw );
        if ( '' === $raw ) {
            return [];
        }

        $decoded = json_decode( $raw, true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            $error_message = __( 'Query arguments must be valid JSON.', 'db-connection-manager' );
            return null;
        }

        return is_array( $decoded ) ? $decoded : [];
    }

    private function queue_admin_notice( $message ) {
        if ( $this->notice_queued ) {
            return;
        }

        set_transient( $this->get_notice_key(), wp_strip_all_tags( $message ), MINUTE_IN_SECONDS );
        add_filter( 'redirect_post_location', [ $this, 'add_notice_query_arg' ] );
        $this->notice_queued = true;
    }

    public function add_notice_query_arg( $location ) {
        remove_filter( 'redirect_post_location', [ $this, 'add_notice_query_arg' ] );
        return add_query_arg( 'dbcm_notice', 1, $location );
    }

    public function render_admin_notice() {
        if ( ! isset( $_GET['dbcm_notice'] ) ) {
            return;
        }

        $message = get_transient( $this->get_notice_key() );
        if ( ! $message ) {
            return;
        }

        delete_transient( $this->get_notice_key() );

        echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
    }

    private function get_notice_key() {
        $user_id = get_current_user_id();

        return 'dbcm_notice_' . ( $user_id ? $user_id : '0' );
    }
}

new DB_Connection_Manager();
