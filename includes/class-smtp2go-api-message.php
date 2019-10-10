<?php
namespace Smtp2Go;

/**
 * Creates an email message payload to send through the request api
 *
 * @link       https://thefold.nz
 * @since      1.0.0
 * @package    Smtp2go_Wordpress_Plugin
 * @subpackage Smtp2go_Wordpress_Plugin/include
 */
class Smtp2GoApiMessage implements SmtpApi2GoRequestable
{
    /**
     * The api key
     *
     * @var string
     */
    protected $api_key;

    /**
     * Custom headers
     *
     * @var array
     */
    protected $custom_headers;

    /**
     * Sender RFC-822 formatted email "John Smith <john@example.com>"
     *
     * @var string
     */
    protected $sender;

    /**
     * the email recipients
     *
     * @var string|array
     */
    protected $recipients;

    /**
     * The CC'd recipients
     *
     * @var string|array
     */
    protected $cc;

    /**
     * The BCC'd recipients
     *
     * @var string|array
     */
    protected $bcc;

    /**
     * The email subject
     *
     * @var string
     */
    protected $subject;

    /**
     * The email message
     *
     * @var string
     */
    protected $message;

    /**
     * Custom email headers
     *
     * @var string|array
     */
    protected $headers;

    /**
     * The data parsed from the $wp_headers
     *
     * @var array
     */
    private $parsed_headers;

    /**
     * The data parsed from the $wp_attachments
     *
     * @var array
     */
    private $parsed_attachments;

    /**
     * Attachments not added through the $wp_attachments variable
     *
     * @var string|array
     */
    protected $attachments;

    /**
     * endpoint to send to
     *
     * @var string
     */
    protected $endpoint = 'email/send';

    /**
     * Create instance - arguments mirror those of the wp_mail function
     *
     * @param mixed $wp_recipients
     * @param mixed $wp_subject
     * @param mixed $wp_message
     * @param mixed $wp_headers
     * @param mixed $wp_attachments
     */
    public function __construct($wp_recipients, $wp_subject, $wp_message, $wp_headers = '', $wp_attachments = array())
    {
        $this->setRecipients($wp_recipients)->setSubject($wp_subject)->setMessage($wp_message);

        $this->processWpHeaders($wp_headers);

        $this->processWpAttachments($wp_attachments);
    }

    /**
     * Process the headers passed in from wordpress
     *
     * @param mixed $wp_headers
     * @return void
     */
    protected function processWpHeaders($wp_headers)
    {

        $compat = new Smtp2GoWpmailCompat;

        $this->parsed_headers = $compat->processHeaders($wp_headers);
    }

    /**
     * Process the attachments passed in from wordpress
     *
     * @param mixed $wp_attachments
     * @return void
     */
    protected function processWpAttachments($wp_attachments)
    {

        $compat = new Smtp2GoWpmailCompat;

        $this->parsed_attachments = $compat->processAttachments($wp_attachments);
    }

    /**
     * initial the instance with values from the plugin options page
     *
     * @since 1.0.0
     * @return void
     */
    public function initFromOptions()
    {
        $this->setSender(get_option('smtp2go_from_name'), get_option('smtp2go_from_address'));
        $this->setCustomHeaders(get_option('smtp2go_custom_headers'));
    }


    /**
     * Builds the JSON to send to the Smtp2go API
     *
     * @return void
     */
    public function buildRequestPayload()
    {
        /** the body of the request which will be sent as json */
        $body = array();

        // $body['api_key'] = $this->getApiKey();

        $body['to']  = $this->buildRecipientsArray();
        $body['cc']  = $this->buildCCArray();
        $body['bcc'] = $this->buildBCCArray();

        $body['sender']         = $this->getSender();

        //@todo handle these better
        
        $body['text_body']      = $this->getMessage();
        
        //nl2br if no tags maybe?
        $body['html_body']      = $this->getMessage();
        

        $body['custom_headers'] = $this->buildCustomHeadersArray();
        $body['subject']        = $this->getSubject();
        $body['attachments']    = $this->buildAttachmentsArray();

        return array(
            'method' => 'POST',
            'body'   => $body,
        );
    }

    public function buildAttachmentsArray()
    {

        $helper = new Smtp2GoMimetypeHelper;

        $attachments = array();

        foreach ((array) $this->attachments as $path) {
            $attachments[] = array(
                'filename' => basename($path),
                'fileblob' => base64_encode(file_get_contents($path)),
                'mimetype' => $helper->getMimeType($path),
            );
        }
        foreach ($this->parsed_attachments as $path) {
            $attachments[] = array(
                'filename' => basename($path),
                'fileblob' => base64_encode(file_get_contents($path)),
                'mimetype' => $helper->getMimeType($path),
            );
        }
        return $attachments;
    }
    /**
     * Build an array of bcc recipients by combining ones natively set
     * or passed through the $wp_headers constructor variable
     *
     * @since 1.0.0
     * @return array
     */

    public function buildCCArray()
    {
        $cc_recipients = array();
        foreach ((array) $this->cc as $cc_recipient) {
            $cc_recipients[] = $this->rfc822($cc_recipient);
        }
        foreach ($this->parsed_headers['cc'] as $cc_recipient) {
            $cc_recipients[] = $this->rfc822($cc_recipient);
        }
        return $cc_recipients;
    }

    /**
     * Build an array of bcc recipients by combining ones natively set
     * or passed through the $wp_headers constructor variable
     *
     * @since 1.0.0
     * @return array
     */
    public function buildBCCArray()
    {
        $bcc_recipients = array();
        foreach ((array) $this->bcc as $bcc_recipient) {
            $bcc_recipients[] = $this->rfc822($bcc_recipient);
        }
        foreach ($this->parsed_headers['bcc'] as $bcc_recipient) {
            $bcc_recipients[] = $this->rfc822($bcc_recipient);
        }
        return $bcc_recipients;
    }

    private function rfc822($email)
    {
        //if its just a plain old email wrap it up
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '<' . $email . '>';
        }
        //pray for good things
        return $email;
    }

    public function buildCustomHeadersArray()
    {
        $raw_custom_headers = $this->getCustomHeaders();

        $custom_headers = array();

        if (!empty($raw_custom_headers['header'])) {
            foreach ($raw_custom_headers['header'] as $index => $header) {
                if (!empty($header) && !empty($raw_custom_headers['value'][$index])) {
                    $custom_headers[] = array(
                        'header' => $header,
                        'value'  => $raw_custom_headers['value'][$index],
                    );
                }
            }
        }
        if (!empty($this->parsed_headers['headers'])) {
            foreach ((array) $this->parsed_headers['headers'] as $name => $content) {
                $custom_headers[] = array(
                    'header' => $name,
                    'value'  => $content,
                );
            }
            //not sure we want this?
            if (false !== stripos($this->parsed_headers['content_type'], 'multipart') && !empty($this->parsed_headers['boundary'])) {
                $custom_headers[] = array(
                    'header'  => 'Content-Type: ' . $this->parsed_headers['content_type'],
                    'content' => 'boundary="' . $this->parsed_headers['boundary'] . '"',
                );
            }
        }

        if (!empty($this->parsed_headers['reply-to'])) {
            $custom_headers[] = array(
                'header' => 'Reply-To',
                'value'  => $this->parsed_headers['reply-to'],
            );
        }

        return $custom_headers;
    }
    /**
     * create an array of recipients to send to the api
     * @todo check how these are formatted and parse appropriately
     * @return void
     */
    public function buildRecipientsArray()
    {
        $recipients = array();

        if (!is_array($this->recipients)) {
            $recipients[] = $this->rfc822($this->recipients);
        } else {
            foreach ($this->recipients as $recipient_item) {
                $recipients[] = $this->rfc822($recipient_item);
            }
        }
        return $recipients;
    }

    /**
     * Get endpoint to send to
     *
     * @return  string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * Set endpoint to send to
     *
     * @param  string  $endpoint  endpoint to send to
     *
     * @return  self
     */
    public function setEndpoint(string $endpoint)
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * Get the api key
     *
     * @return  string
     */
    public function getApiKey()
    {
        return $this->api_key;
    }

    /**
     * Set the api key
     *
     * @param  string  $api_key  The api key
     *
     * @return  self
     */
    public function setApiKey(string $api_key)
    {
        $this->api_key = $api_key;

        return $this;
    }

    /**
     * Get custom headers - expected format is the unserialized array
     * from the stored smtp2go_custom_headers option
     *
     * @return  array
     */
    public function getCustomHeaders()
    {
        return $this->custom_headers;
    }

    /**
     * Set custom headers - expected format is the unserialized array
     * from the stored smtp2go_custom_headers option
     *
     * @param  array  $custom_headers  Custom headers
     *
     * @return  self
     */
    public function setCustomHeaders(array $custom_headers)
    {
        $this->custom_headers = $custom_headers;

        return $this;
    }

    /**
     * Get sender
     *
     * @return  string
     */
    public function getSender()
    {
        return $this->sender;
    }

    /**
     * Set sender as RFC-822 formatted email "John Smith <john@example.com>"
     *
     * @param string $email
     * @param string $name
     *
     * @return self
     */
    public function setSender($email, $name = '')
    {
        if (!empty($name)) {
            $email        = str_replace(['<', '>'], '', $email);
            $this->sender = "$name <$email>";
        } else {
            $this->sender = "$email";
        }

        return $this;
    }

    /**
     * Get the email subject
     *
     * @return  string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Set the email subject
     *
     * @param  string  $subject  The email subject
     *
     * @return  self
     */
    public function setSubject(string $subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Get the email message
     *
     * @return  string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set the email message
     *
     * @param  string  $message  The email message
     *
     * @return  self
     */
    public function setMessage(string $message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Get the email recipients
     *
     * @return  string|array
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

    /**
     * Set the email recipients
     *
     * @param  string|array  $recipients  the email recipients
     *
     * @return  self
     */
    public function setRecipients($recipients)
    {
        if (is_string($recipients)) {
            $this->recipients = $recipients;
        }

        return $this;
    }

    /**
     * Get the BCC'd recipients
     *
     * @return  string|array
     */
    public function getBcc()
    {
        return $this->bcc;
    }

    /**
     * Set the BCC'd recipients
     *
     * @param  string|array  $bcc  The BCC'd recipients
     *
     * @return  self
     */
    public function setBcc($bcc)
    {
        $this->bcc = $bcc;

        return $this;
    }

    /**
     * Get the CC'd recipients
     *
     * @return  string|array
     */
    public function getCc()
    {
        return $this->cc;
    }

    /**
     * Set the CC'd recipients
     *
     * @param  string|array  $cc  The CC'd recipients
     *
     * @return  self
     */
    public function setCc($cc)
    {
        $this->cc = $cc;

        return $this;
    }

    /**
     * Get attachments not added through the $wp_attachments variable
     *
     * @return  string|array
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * Set attachments not added through the $wp_attachments variable
     *
     * @param  string|array  $attachments Attachments not added through the $wp_attachments variable
     *
     * @return  self
     */
    public function setAttachments($attachments)
    {
        $this->attachments = $attachments;

        return $this;
    }
}
