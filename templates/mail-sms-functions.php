<?php
// Mail and SMS functions for ReTree Hawaii
// This file is part of the idp-client package

require_once __DIR__ . '/vendor/autoload.php';

use MailService\Client as MailServiceClient;

define ("ATTACHMENT_DIR", "/tmp/uploads" );

// Legacy compatibility - MAILER no longer used but kept for backward compatibility
define( 'MAILER', 1 );

function sendMailMessage( $to, $toname, $subject, $message ) {
    $mailparams = [
        'to' => $to,
        'toname' => $toname,
        'fromname' => 'ReTree Hawaii',
        'from' => 'info@retree-hawaii.org',
        'subject' => $subject,
        'message' => $message,
        'groupid' => uniqidReal( "mail" ),
        'mailid' => uniqidReal( "msg" ),
    ];
    return sendMail( $mailparams );
}

function sendMultipleRecipients( $sendername, $senderemail, $all, $subject, $message ) {
    error_log( "sendMultipleRecipients: $sendername, $senderemail, " . var_export( $all, true ) . ", $subject, $message" );
    
    $recipients = [];
    foreach( $all as $entry ) {
        $record = (array)$entry;
        $recipients[] = $record['recipient'];
    }
    $groupid = uniqidReal( "mail" );
    
    $status = 0;
    foreach( $all as $entry ) {
        $record = (array)$entry;
        $email = $record['recipient'];
        $name = $record['name'];
        if( $email ) {
            $record = extendRecord( $record );
            $m = expandMessage( $message, $record );
            $s = expandMessage( $subject, $record );
            $mailparams = [
                'to' => $email,
                'toname' => $name,
                'fromname' => $sendername,
                'from' => $senderemail,
                'subject' => $s,
                'message' => $m,
                'groupid' => $groupid,
                'mailid' => uniqidReal( "msg" ),
            ];
            $status = sendMail( $mailparams );
        }
    }
    return $status;
}

function sendMail( $mailparams ) {
    error_log( "sendMail: " . var_export( $mailparams, true ) );
    
    $to = $mailparams['to'];
    $toname = $mailparams['toname'];
    $from = $mailparams['from'];
    $fromname = $mailparams['fromname'];
    $subject = $mailparams['subject'];
    $message = $mailparams['message'];
    
    try {
        // Use the MailServiceClient for sending emails
        $client = getMailServiceClient();
        
        // Handle attachments if provided (TODO: implement in mail-service)
        if( !empty($mailparams['attachments']) ) {
            error_log("Warning: Attachments not yet supported in mail-service migration: " . json_encode($mailparams['attachments']));
        }
        
        // Use the new send() method interface
        $response = $client->send($to, $subject, $message, null, null, $client->clientId);
        
        if ($response['success']) {
            // Mail-service handles all logging, no need for local logMailMessage()
            error_log( "Sent '" . $subject . "' to $toname <" . $to . ">" );
            return 0;
        } else {
            error_log( "Failed to send email to $to; Error: " . ($response['error'] ?? 'Unknown error') );
            return $response['error'] ?? 'Unknown error';
        }
    } catch (Exception $e) {
        error_log( "Mail service error in sendMail: " . $e->getMessage() );
        return $e->getMessage();
    }
}


function addFooter( $message, $record, $mailparams ) {
    global $config, $orgname;
    $footerStyle = "font-style:italic;font-size:8pt;";
    $message .= "<div id='footer'>\n";
    // Append unsubscribe line for lead mail
    if( isset($record['class']) && strtolower($record['class']) == 'lead' ) {
        $message .=
            "<br /><br /><hr><p style='$footerStyle'>" .
            "If you do not wish to receive further email from " . $config['orgname'] . ", you can unsubscribe " .
            "<a href='" . $config["baseurl"] . "/unsubscribe?email=" . $record['email'] . "'>here</a></p>";
    }
    // Add beacon
    $message .= "<img width='1' height='1' style='width:1px;height:1px;' src='" . $config["baseurl"] . "/1pixel?id=" .
                $mailparams['mailid'] . "&groupdid=" . $mailparams['groupid'] .
                "' />";
    $message .= "\n</div>";
    return $message;
}

function updateLeadStatus( $entry, $status ) {
    if( !$status && $entry && ($entry->class == 'lead') && !$entry->status ) {
        debuglog( $entry, "updateLeadStatus entry" );
        $out = (array)$entry;
        $out['status'] = "Contacted";
        debuglog( $out, "updateLeadStatus out" );
        $lead = new Lead();
        $lead->update( $out );
    }
}

function addNote( $entry, $subject, $content ) {
    if( $entry && $entry->id ) {
        $now = date("F d, Y h:i A" );
        $title = "Sent email on " . $now . ": " . $subject;
        $s = classForName( $entry->class );
        debuglog( $entry, "addNote entry" );
        if( $s ) {
            $s->addNote( $entry->id, $title, $content );
        }
    }
}

function logMailMessage( $mailparams ) {
    // Mail logging is now handled by mail-service
    // This function is kept for backward compatibility but does nothing
    debuglog( $mailparams, "logMailMessage mailparams - delegated to mail-service" );
    
    // Ensure required IDs are generated for backward compatibility
    if( !isset( $mailparams['groupid'] ) ) {
        $mailparams['groupid'] = uniqidReal( "mail" );
    }
    if( !isset( $mailparams['mailid'] ) ) {
        $mailparams['mailid'] = uniqidReal( "msg" );
    }
    
    // Return success since mail-service handles all logging
    return true;
}

/**
 * Encode an email address to display on a website
 */
/*
   function encode_email_address( $email ) {
   $output = '';
   for ($i = 0; $i < strlen($email); $i++) {
   $output .= '&#'.ord($email[$i]).';';
   }
   return $output;
   }
 */

function send( $allEntries, $message, $mailparams ) {
    debuglog( $allEntries, 'mailfuncs:send allEntries' );
    debuglog( $mailparams, 'mailfuncs:send mailparams' );
    global $forreal;
    $isreal = $forreal;
    if( isset( $mailparams['forreal'] ) ) {
        $isreal = $mailparams['forreal'];
    }
    $mailparams['groupid'] = uniqidReal( "mail" );
    $failed = array();
    $succeeded = array();

    $addressees = array();
    foreach( $allEntries as $entry ) {
        $email = $entry->email;
        if( $email ) {
            array_push( $addressees, $email );
        }
    }
    $mailparams['to'] = implode( ',', $addressees );
    $mailparams['toname'] = 'Master';
    
    //var_dump( $addressees );
    foreach( $allEntries as $entry ) {
        //echo( "mailfuncs entry: " . var_export( $entry, true ) . "\n" );
        if( $entry->email ) {
            
            $mailparams['to'] = $email = $entry->email;
            $mailparams['toname'] = $name = $entry->name;
            $mailparams['fulladdress'] = $fulladdress = $name . " <" . $email . ">";
            $mailparams['mailid'] = uniqidReal( "msg" );

            $record = extendRecord( (array)$entry );
            
            // Content
            $expanded_message = expandMessage( $message, $record );
            $expanded_message = addFooter( $expanded_message, $record, $mailparams );
            $mailparams['message'] = $expanded_message;
            $mailparams['attachment'] = '';
            $files = $_FILES['attachment'];
            if( $files && sizeof( $files['name'] ) > 0 ) {
                $filenames = "";
                mkdir( ATTACHMENT_DIR, 0777 );
                for( $i = 0; $i < sizeof( $files['name'] ); $i++ ) {
                    $file = $files['name'][$i];
                    $destination = ATTACHMENT_DIR . "/$file";
                    move_uploaded_file( $files['tmp_name'][$i], $destination );
                    $filenames .= $destination . "|";
                }
                trim( $filenames, " |\n\r\t\v\0" );
                $mailparams['attachment'] = $filenames;
            }
            debuglog( $mailparams, "mailfuncs.php send mailparams" );
            if( $isreal ) {
                echo $fulladdress . "\n";
                flush();
                ob_flush();
                if( $mailparams['immediate'] ) {
                    $result = sendMail( $mailparams );
                } else {
                    // Schedule the sending
                    $result = submitEmailJob( $mailparams, $mailparams['sendtime'] );
                }
                if( $result ) {
                    debuglog( "Failed to submit email to $fulladdress; $result", "send: mailfuncs.php" );
                    array_push( $failed, $fulladdress );
                } else {
                    if( $mailparams['immediate'] ) {
                        debuglog( $entry, "send: mailfuncs.php: Sent email to $fulladdress" );
                    } else {
                        debuglog( $entry, "send: mailfuncs.php: Scheduled email to $fulladdress for " .
                                          $mailparams['sendtime'] );
                    }
                    updateLeadStatus( $entry, $entry->status );
                    $plain = strip_tags( $expanded_message );
                    addNote( $entry, $mailparams['subject'], $plain );
                    array_push( $succeeded, $fulladdress );
                }
            } else {
                array_push( $succeeded, $fulladdress );
                echo "from: " . $mailparams['fromname'] . " - " . $mailparams['from'] . " to: $name  - $email\n";
            }
        }
    }
    return [
        'failed' => $failed,
        'succeeded' => $succeeded,
    ];
}

function mailBatch( $records, $templateID, $from, $fromName, $subject = "", $test = false ) {

    $toSend = [];
    foreach( $records as $lead ) {
        $lead['sender name'] = $fromName;
        array_push( $toSend, (object)$lead );
    }
    //echo var_export( $toSend, true ) . "\n\n";
    //return;
    
    $t = new Template();
    $template = $t->getById( $templateID );
    if( !$template || !isset( $template['text']) ) {
        echo "Invalid template ID: $templateID\n";
        return;
    }

    if( $test ) {
        echo( "Template $templateID: " . var_export( $template, true ) . "\n\n" );
        echo( var_export( $toSend, true ) . "\n\n" );
        //return;
    }
    
    $message = $template['text'];
    $message = html_entity_decode( $message );
    if( !$subject ) {
        $subject = $template['subject'];
    }
    $fromAddress = $fromName . " <" . $from . ">";

    $mailparams = [
        'from' => $from,
        'fromname' => $fromName,
        'fromaddress' => $fromAddress,
        'subject' => $subject,
        'immediate' => false,
        'forreal' => true,
        //'cc' => $orgemail,
        //'bcc' => $orgemail,
    ];
    //echo( var_export( $toSend, true ) . "\n" );

    $mailparams['forreal'] = ($test != 0 ) ? false : true;
    //echo( "test: $test, mailparams: " . var_export( $mailparams, true ) . "\n" );
    //return;

    $result = send( $toSend, $message, $mailparams );

    $failed = $result['failed'];
    $succeeded = $result['succeeded'];
    if( sizeof($failed) > 0 ) {
        echo "\n" . sizeof($succeeded) . " of " . (sizeof($succeeded) + sizeof($failed)) . " messages sent. The following failed\n";
        foreach( $failed as $addr ) {
            echo $addr . "\n";
        }
        echo "The following succeeded\n";
        foreach( $succeeded as $addr ) {
            echo $addr . "\n";
        }
    } else {
        echo "\n" . sizeof($succeeded) . " messages sent.\n";
    }
}

?>
