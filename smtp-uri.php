<?php
/*
Plugin Name: SMTP URI
Plugin URI: https://github.com/szepeviktor
Description: Set SMTP options from the SMTP_URI named constant.
Version: 0.4.2
License: The MIT License (MIT)
Author: Viktor Szépe
Author URI: http://www.online1.hu/webdesign/
GitHub Plugin URI: https://github.com/szepeviktor/smtp-uri
*/

/*
@TODO Add DKIM header
@TODO Option to skip newsletters.
            "X-ALO-EM-Newsletter"  /?emtrck=  /?emunsub=  /plugins/alo-easymail/tr.php?v=
            Newsletter
            Mailpoet
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

    public function __construct() {

        // Set SMTP options
        add_action( 'phpmailer_init', array( $this, 'smtp_options' ), 4294967295 );
        // Handle last error
        add_action( 'shutdown', array( $this, 'handle_error' ), 4294967295 );
        // Settings in Options/Reading
        add_action( 'admin_init', array( $this, 'settings_init' ) );

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

        $smtp_uri = $this->get_smtp_uri();
        if ( empty( $smtp_uri ) ) {
            return;
        }

        $uri = parse_url( $smtp_uri );

        // Protocol and encryption
        switch ( strtolower( $uri['scheme'] ) ) {
            case 'smtp':
                $mail->SMTPSecure = '';
                $mail->Port = 25;
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
        if ( empty( $uri['host'] ) ) {
            return;
        }
        $mail->Host = urldecode( $uri['host'] );

        // Port
        if ( is_int( $uri['port'] ) ) {
            $mail->Port = $uri['port'];
        }

        // Authentication
        if ( ! empty( $uri['user'] ) && ! empty( $uri['pass'] ) ) {
            $mail->SMTPAuth = true;
            $mail->Username = urldecode( $uri['user'] );
            $mail->Password = urldecode( $uri['pass'] );
        }

        $mail->isSMTP();

        // Turn on SMTP debugging
        //$mail->SMTPDebug = 4;
        //$mail->Debugoutput = 'error_log';

        // Bcc admin email
        //$mail->addBCC( get_bloginfo( 'admin_email' ) );
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

        $error_message = sprintf( "SMTP error: To,Cc,Bcc=%s Message=%s",
            $this->esc_log( implode( ',' , array( $to, $cc, $bcc ) ) ),
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
            $error_message =  sprintf( "SMTP error: %s", $this->esc_log( $this->phpmailer->ErrorInfo ) );

            error_log( $error_message );

            if ( class_exists( 'SucuriScanEvent' ) ) {
                if ( true !== SucuriScanEvent::report_critical_event( $error_message ) ) {
                    error_log( "Sucuri Scan report event failure." );
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
            'SMTP URI',
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
        $disabled = defined( 'SMTP_URI' ) ? ' disabled' : '';

        printf( '<input name="smtp_uri" id="smtp_uri" type="text" class="regular-text code" value="%s"%s/>',
            $smtp_uri,
            $disabled
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

        $escaped = serialize( $data ) ;
        // Limit length
        $escaped = mb_substr( $escaped, 0, 500, 'utf-8' );
        // New lines to "|"
        $escaped = str_replace( "\n", "|", $escaped );
        // Replace non-printables with "¿"
        $escaped = preg_replace( '/[^\P{C}]+/u', "\xC2\xBF", $escaped );

        return $escaped;
    }
}

new O1_Smtp_Uri;
