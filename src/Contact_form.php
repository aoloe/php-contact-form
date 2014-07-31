<?php

namespace Aoloe;

class Contact_form {

    private $request_prefix = "contact_form_";
    public function set_request_prefix($prefix) {$this->request_prefix = $prefix;}
    public function get_request_prefix() {return $this->request_prefix;}
    private $field_request = array (
        'name' => null,
        'email' => null,
        'subject' => null,
        'message' => null,
    );

    private $field_required = array (
        'message' => true,
    );

    private $field_valid_email = array (
        'email' => true,
    );

    private $field_request_file = array();

    // error messages?
    private $field_message = array();

    private $mail_from = null;
    function set_mail_from($email) {$this->mail_from = $email;}

    private $mail_to = "";
    function set_mail_to($email) {$this->mail_to = $email;}

    private $subject_prefix = "[Contact Form]";
    public function set_subject_prefix($prefix) {$this->subject_prefix = $prefix;}

    public function Contact_form() {
    }

    public function is_submitted() {
        return array_key_exists($this->request_prefix.'submit', $_REQUEST);
    }

    public function add_field($name, $value = null) {
        if (isset($value)) {
            $this->field_post[$name] = $value;
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
            foreach ($this->field_request_file as $item) {
                $request_key = $this->request_prefix.$item;
                if (array_key_exists($request_key, $_FILES) && !empty($_FILES[$request_key])) {
                    // TODO: implement the files handling
                    /*
                    'name' => $_FILES[$this->post_prefix.'file']['name'],
                    'type' =>  $_FILES[$this->post_prefix.'file']['type'],
                    'location' => $_FILES[$this->post_prefix.'file']['tmp_name'],
                    */
                }
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
        debug('field_required', $this->field_required);
        foreach ($this->field_valid_email as $key => $value) {
            $this->field_valid_email[$key] = (empty($this->field[$key]) || (strpos($this->field[$key], '@') !== false));
            $result &= $this->field_valid_email[$key];
        }
        debug('field_valid_email', $this->field_valid_email);
        return $result;
    }

    public function is_spam() {
        $result = false;
        // TODO: eventually add an honoey pot field that must be left empty...
        $result &= strpos($this->field['subject'], "MIME-Version") !== false;
        $result &= strpos($this->field['subject'], "Content-Type") !== false;
        $result &= strpos($this->field['email'], "MIME-Version") !== false;
        $result &= strpos($this->field['message'], "MIME-Version") !== false;
        $result &= ($this->field['email'] == $this->field['subject']) && ($this->field['email'] == $this->field['message']);
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
        if (empty($this->field['file'])) {
            $result = mail(
                $this->mail_to,
                $this->subject_prefix.$this->field['subject'],
                $this->field['message'],
                "From: ".$this->field['email']
            );
        } else {
            include_once(LIB_PATH.'/htmlMimeMail.php');
            $mail = new htmlMimeMail();
            $attachment = $mail->getFile($this->field['file']['location']);
            $mail->addAttachment($attachment, $this->field['file']['name'], $this->field['file']['type']);
            $mail->setFrom($this->field['email']);
            $mail->setSubject($this->subject_prefix.$this->field['subject']);
            $mail->setText($this->content);
            $mail->setHeader('X-Mailer', 'HTML Mime mail class (http://www.phpguru.org)');
            $result = $mail->send(array($this->target));
        }
        return $result;
    }
}
