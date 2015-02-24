<?php

namespace Aoloe;


error_reporting(E_ALL);
ini_set('display_errors', 1);

class Contact_form {

    private $force_smtp = false;
    public function set_force_smtp($force = true) {$this->force_smtp = $force;}

    private $request_prefix = "contact_form_";
    public function set_request_prefix($prefix) {$this->request_prefix = $prefix;}
    public function get_request_prefix() {return $this->request_prefix;}
    public function clear_field_request() {
        $this->field_request = array();
        $this->field_required = array();
        $this->field_valid_email = array();
    }
    public function add_field_request($name, $default = null) {
        $this->field_request[$name] = $default;
    }
    private $field_request = array (
        'name' => null,
        'email' => null,
        'subject' => null,
        'message' => null,
    );

    public function set_field_required($name) {
        $this->field_required[$name] = true;
    }
    private $field_required = array (
        'message' => true,
    );

    public function set_field_valid_email($name) {
        $this->field_valid_email[$name] = true;
    }
    private $field_valid_email = array (
        'email' => true,
    );

    public function add_field_request_file($name) {
        $this->field_request_file[] = $name;
    }
    private $field_request_file = array();

    // error messages?
    private $field_message = array();

    private $mail_from = null;
    function set_mail_from($email) {$this->mail_from = $email;}

    private $mail_to = "";
    function set_mail_to($email) {$this->mail_to = $email;}

    private $subject_prefix = "[Contact Form]";
    public function set_subject_prefix($prefix) {$this->subject_prefix = $prefix;}

    private $subject = null;
    public function set_subject($subject) {$this->subject = $subject;}

    private $message = "";
    public function set_message($message) {$this->message = $message;} // TODO: do we really need both message and contetn?

    private $content = null;
    public function set_content($content) {$this->content = $content;}

    public function is_submitted() {
        return array_key_exists($this->request_prefix.'submit', $_REQUEST);
    }

    /**
     * TODO: check if this function is really useful / used
     */
    public function add_field($name, $value = null) {
        if (isset($value)) {
            $this->field[$name] = $value;
        }
    }

    public function get_field($field = null) {
        if (isset($field)) {
            return $this->field[$field];
        } else {
            return $this->field;
        }
    }

    public function read() {
        // debug('field_request', $this->field_request);
        foreach ($this->field_request as $key => $value) {
            $request_key = $this->request_prefix.$key;
            if (array_key_exists($request_key, $_REQUEST)) {
                $this->field[$key] = $_REQUEST[$request_key];
            } elseif (isset($value)) {
                $this->field[$key] = $value;
            }
        }
        foreach ($this->field_request_file as $item) {
            // debug('item', $item);
            // debug('_FILES', $_FILES);
            $request_key = $this->request_prefix.$item;
            if (array_key_exists($request_key, $_FILES) && !empty($_FILES[$request_key])) {
                $this->field[$item] = array (
                    'name' => $_FILES[$this->request_prefix.$item]['name'],
                    'type' =>  $_FILES[$this->request_prefix.$item]['type'],
                    'location' => $_FILES[$this->request_prefix.$item]['tmp_name'],
                );
            }
        }
        // debug('field', $this->field);
    }

    public function get($name) {
        return $this->field[$name];
    }
    
    public function is_valid() {
        $result = true;
        // debug('field_required', $this->field_required);
        foreach ($this->field_required as $key => $value) {
            $this->field_required[$key] = !empty($this->field[$key]);
            $result &= $this->field_required[$key];
        }
        // debug('field_required', $this->field_required);
        foreach ($this->field_valid_email as $key => $value) {
            $this->field_valid_email[$key] = (empty($this->field[$key]) || (strpos($this->field[$key], '@') !== false));
            $result &= $this->field_valid_email[$key];
        }
        // debug('field_valid_email', $this->field_valid_email);
        return $result;
    }

    public function is_spam() {
        $result = false;
        // TODO: eventually add an honoey pot field that must be left empty...
        $result &= !array_key_exists('subject', $this->field_request) || (strpos($this->field['subject'], "MIME-Version") !== false);
        $result &= !array_key_exists('subject', $this->field_request) || (strpos($this->field['subject'], "Content-Type") !== false);
        $result &= !array_key_exists('email', $this->field_request) || (strpos($this->field['email'], "MIME-Version") !== false);
        $result &= !array_key_exists('message', $this->field_request) || (strpos($this->field['message'], "MIME-Version") !== false);
        if (array_key_exists('email', $this->field) && array_key_exists('subject', $this->field)) {
            $result &= ($this->field['email'] == $this->field['subject']);
        }
        if (array_key_exists('email', $this->field) && array_key_exists('message', $this->field)) {
            $result &= ($this->field['email'] == $this->field['message']);
        }
        return $result;
    }

    public function fill_message() {
        $this->content = "";
        foreach($this->field as $key => $value) {
            if (!empty($value)) {
                if ($key == 'file') {
                    $value = $value['name'];
                }
                $this->content .= str_replace("_", " ", $key).": ".$value."\n";
            }
        }
    }

    function send($server = "") {
        $result = true;
        // debug('field', $this->field);
        if (!$this->force_smtp && empty($this->field_request_file)) {
            $result = mail(
                $this->mail_to,
                $this->subject_prefix. (array_key_exists('subject', $this->field) ? $this->field['subject'] : $this->subject),
                array_key_exists('message', $this->field) ? $this->field['message'] : $this->message,
                "From: ".$this->field['email']
            );
            // debug('mail() result', $result);
        } else {
            // debug('field_request_file', $this->field_request_file);
            $mail = new \PHPMailer();
            $mail->SMTPDebug  = 2;
            foreach ($this->field_request_file as $item) {
                // debug('field[key]', $this->field[$item]);
                if (!empty($this->field[$item])) {
                    $mail->AddAttachment($this->field[$item]['location'], $this->field[$item]['name'], 'base64', $this->field[$item]['type']);
                }
            }
            $mail->addAddress($this->mail_to);
            $mail->setFrom(array_key_exists('email', $this->field) ? $this->field['email'] : $this->mail_from);
            $mail->Subject = $this->subject_prefix.' '.(isset($this->subject) ? $this->subject : '');
            $mail->CharSet = 'UTF-8';
            if (isset($this->content)) {
                $mail->Body = $this->content;
            } else {
                $body = array();
                // debug('field_request', $this->field_request);
                // debug('field', $this->field);
                foreach ($this->field_request as $key => $value) {
                    $body[] = $key.': '.$this->field[$key];
                }
                $mail->Body = implode("\n\n", $body);
            }
            // debug('body', $mail->Body);
            $result = $mail->send();
            // debug('result', $result);
            // debug('ErrorInfo', $mail->ErrorInfo);
            /*
            $attachment = $mail->getFile($this->field['file']['location']);
            $mail->addAttachment($attachment, $this->field['file']['name'], $this->field['file']['type']);
            $mail->setFrom($this->field['email']);
            $mail->setSubject($this->subject_prefix.$this->field['subject']);
            $mail->setText($this->content);
            $mail->setHeader('X-Mailer', 'HTML Mime mail class (http://www.phpguru.org)');
            $result = $mail->send(array($this->target));
            */
        }
        // debug('result', $result);
        return $result;
    }
}
