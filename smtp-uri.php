<?php
/*
Plugin Name: SMTP URI and logging
Version: 0.4.8
Description: SMTP settings for WordPress and error logging.
Plugin URI: https://github.com/szepeviktor/smtp-uri
License: The MIT License (MIT)
Author: Viktor Szépe
GitHub Plugin URI: https://github.com/szepeviktor/smtp-uri
*/

/* @TODO
    Option to skip newsletters
        "X-ALO-EM-Newsletter"  /?emtrck=  /?emunsub=  /plugins/alo-easymail/tr.php?v=
        Newsletter
        Mailpoet
    Add $phpmailer->Timeout
    Add DKIM header
*/

/**
 * Set SMTP options and log sending errors.
 *
 * For setting From: name and From: address use WP Mail From II plugin.
 *
 * @see: https://wordpress.org/plugins/wp-mailfrom-ii/
 */
class O1_Smtp_Uri {

    private $phpmailer = null;
    private $from = null;

    public function __construct() {

        // Remember From address
        add_filter( 'wp_mail_from', array( $this, 'set_from' ), 4294967295 );
        // Set SMTP options
        add_action( 'phpmailer_init', array( $this, 'smtp_options' ), 4294967295 );
        // Handle last error
        add_action( 'shutdown', array( $this, 'handle_error' ), 4294967295 );

        if ( is_admin() ) {
            // Settings in Options/Reading
            add_action( 'admin_init', array( $this, 'settings_init' ) );
            // "Settings" link on Plugins page
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_link' ) );
        }
    }

    public function set_from( $from ) {

        $this->from = $from;

        return $from;
    }


    /**
     * Set PHPMailer SMTP options from the SMTP_URI named constant.
     *
     * @see: https://wordpress.org/plugins/wp-mailfrom-ii/
     * @param: object $mail PHPMailer instance.
     * @return void
     */
    public function smtp_options( $mail ) {

        // Handle error of the previous mail sending
        $this->handle_error();

        // Save PHPMailer object for logging the error message
        $this->phpmailer = $mail;

        // Set callback function for logging message data
        $mail->action_function = array( $this, 'callback' );

        // Remove X-Mailer header
        $mail->XMailer = ' ';

        // Correct invalid From: address
        if ( null !== $this->from && ! $mail->validateAddress( $this->from ) ) {
            $mail->From = get_bloginfo( 'admin_email' );
        }

        $smtp_uri = $this->get_smtp_uri();
        if ( empty( $smtp_uri ) ) {
            return;
        }

        $uri = parse_url( $smtp_uri );
        if ( empty( $uri['scheme'] ) || empty( $uri['host'] ) ) {
            return;
        }

        // Protocol and encryption
        switch ( strtolower( $uri['scheme'] ) ) {
            case 'smtp':
                $mail->SMTPSecure = '';
                $mail->Port = 25;
                // Only explicit encryption
                $mail->SMTPAutoTLS = false;
                break;
            case 'smtps':
                $mail->SMTPSecure = 'ssl';
                $mail->Port = 465;
                break;
            case 'smtptls':
            case 'smtpstarttls':
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                break;
            default:
                return;
        }

        // Host name
        $mail->Host = urldecode( $uri['host'] );

        // Safe to enable SMTP now
        $mail->isSMTP();

        // Port
        if ( ! empty( $uri['port'] ) && is_int( $uri['port'] ) ) {
            $mail->Port = $uri['port'];
        }

        // Authentication
        if ( ! empty( $uri['user'] ) && ! empty( $uri['pass'] ) ) {
            $mail->Username = urldecode( $uri['user'] );
            $mail->Password = urldecode( $uri['pass'] );
            $mail->SMTPAuth = true;
        }

        // Bcc admin email or the specified address
        if ( ! empty( $uri['path'] ) ) {
            if ( '/admin_email' === $uri['path'] ) {
                $mail->addBCC( get_bloginfo( 'admin_email' ) );
            } else {
                $bcc = urldecode( ltrim( $uri['path'], '/' ) );
                if ( $mail->validateAddress( $bcc ) ) {
                    $mail->addBCC( $bcc );
                }
            }
        }

        // Turn on SMTP debugging
        if ( ! empty( $uri['query'] ) ) {
            $query = $this->parse_query( $uri['query'] );
            if ( isset( $query['debug'] ) ) {
                $mail->SMTPDebug = is_numeric( $query['debug'] ) ? (int) $query['debug'] : 4;
                $mail->Debugoutput = 'error_log';
            }
        }
    }

    /**
     * Log message data.
     *
     * @param bool $isSent    Result of the send action
     * @param string $to      Email address of the recipient
     * @param string $cc      Cc email addresses
     * @param string $bcc     Bcc email addresses
     * @param string $subject The subject
     * @param string $body    The email body
     * @param string $from    Email address of the sender
     * @return void
     */
    public function callback( $isSent, $to, $cc, $bcc, $subject, $body, $from ) {

        if ( $isSent ) {
            return;
        }

        $error_message = sprintf( 'SMTP error: To,Cc,Bcc=%s Subject=%s Message=%s',
            $this->esc_log( implode( ',' , array( $to, $cc, $bcc ) ) ),
            $this->esc_log( $subject ),
            $this->esc_log( $body )
        );

        error_log( $error_message );
    }

    /**
     * Log and report mail sending errors.
     *
     * @return void
     */
    public function handle_error() {

        if ( ! is_null( $this->phpmailer )
            && property_exists( $this->phpmailer, 'ErrorInfo' )
            && ! empty( $this->phpmailer->ErrorInfo )
        ) {
            $error_message = sprintf( 'SMTP error: %s', $this->esc_log( $this->phpmailer->ErrorInfo ) );

            error_log( $error_message );

            if ( class_exists( 'SucuriScanEvent' ) ) {
                if ( true !== SucuriScanEvent::report_critical_event( $error_message ) ) {
                    error_log( 'Sucuri Scan report event failure.' );
                }
            }
        }
    }

    /**
     * Register in Settings API
     *
     * @return void
     */
    public function settings_init() {

        add_settings_section(
            'smtp_uri_section',
            'SMTP URI',
            array( $this, 'admin_section' ),
            'general'
        );
        add_settings_field(
            'smtp_uri',
            '<label for="smtp_uri">SMTP URI</label>',
            array( $this, 'admin_field' ),
            'general',
            'smtp_uri_section'
        );
        register_setting( 'general', 'smtp_uri' );
    }

    /**
     * Print the section description for Settings API
     *
     * @return void
     */
    public function admin_section() {

        printf( '<p>The SMTP URI is made up of <strong>encryption, user name, password, host name and port</strong>.<br/>' );
        printf( 'Username, password and port are <strong>optional</strong>.</p>' );
    }

    /**
     * Print the input field for Settings API
     *
     * @return void
     */
    public function admin_field() {

        $smtp_uri = esc_attr( $this->get_smtp_uri() );
        $attrs = defined( 'SMTP_URI' ) ? sprintf( ' disabled title="%s"', $smtp_uri ) : '';

        printf( '<input name="smtp_uri" id="smtp_uri" type="text" class="regular-text code" value="%s"%s/>',
            $smtp_uri,
            $attrs
        );
        printf( '<p class="description">Format: <code>smtpTLS://USERNAME:PASSWORD@HOST:PORT</code></p>' );
        printf( '<p class="description">Enter <code>smtp://</code> for unencrypted connection</p>' );
        printf( '<p class="description"><code>smtpTLS://</code> for encrypted connection (STARTTLS)</p>' );
        printf( '<p class="description"><code>smtps://</code> for fully encrypted connection on port 465</p>' );
    }

    /**
     * Retrieve the URI.
     *
     * @return @void
     */
    private function get_smtp_uri() {

        if ( defined( 'SMTP_URI' ) ) {
            $smtp_uri = SMTP_URI;
        } else {
            $smtp_uri = get_option( 'smtp_uri' );
        }

        return $smtp_uri;
    }

    /**
     * Prepare any data for logging.
     *
     * @param string $string Any data.
     * @return string        The escaped string.
     */
    private function esc_log( $data ) {

        $escaped = serialize( $data );
        // Limit length
        $escaped = mb_substr( $escaped, 0, 500, 'utf-8' );
        // New lines to "|"
        $escaped = str_replace( "\n", '|', $escaped );
        // Replace non-printables with "¿"
        $escaped = preg_replace( '/[^\P{C}]+/u', "\xC2\xBF", $escaped );

        return $escaped;
    }

    /**
     * Parse URL query string to an array.
     *
     * @param string $query_string  The query string
     *
     * @return array                The query as an array
     */
    private function parse_query( $query_string ) {
        $query = array();
        $names_values_array = explode( '&', $query_string );

        foreach ( $names_values_array as $name_value ) {
            $name_value_array = explode( '=', $name_value );

            // Check field name
            if ( empty( $name_value_array[0] ) ) {
                continue;
            }

            // Set field value
            $query[ $name_value_array[0] ] = isset( $name_value_array[1] ) ? $name_value_array[1] : '';
        }

        return $query;
    }

    public function plugin_link( $actions ) {

        $link = sprintf( '<a href="%s">Settings</a>',
            admin_url( 'options-general.php#smtp_uri' )
        );
        array_unshift( $actions, $link );

        return $actions;
    }
}

new O1_Smtp_Uri();
