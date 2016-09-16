<?php

/*
 +-----------------------------------------------------------------------+
 | lib/drivers/plesk.php                                                 |
 |                                                                       |
 | Copyright (C) 2009 Boris HUISGEN <bhuisgen@hbis.fr>                   |
 | Copyright (C) 2011 Vicente MONROIG <vmonroig@digitaldisseny.com>      |
 | Licensed under the GNU GPL                                            |
 +-----------------------------------------------------------------------+

 +-----------------------------------------------------------------------+
   Please, note:
   Needs access for apache user in /etc/sudoers. For example:
   www-data ALL=NOPASSWD: /opt/psa/bin/autoresponder

   Recommended options in vacation config.inc.php:
   $rcmail_config['vacation_gui_vacationdate'] = FALSE;
   $rcmail_config['vacation_gui_vacationsubject'] = TRUE;
   $rcmail_config['vacation_gui_vacationmessage_html'] = TRUE;
   $rcmail_config['vacation_gui_vacationkeepcopyininbox'] = FALSE;
   $rcmail_config['vacation_gui_vacationforwarder'] = TRUE;
 +-----------------------------------------------------------------------+
 */

function parse_plesk_output($output) {

    $mapping = array(
        'Status' => 'enable',
        'Answer with subj:' => 'subject',
        'Format:' => 'format',
        'Charset:' => 'charset',
        'Answer text:' => 'message',
        'Attach files:' => 'ignore',
        'Forward request:' => 'forwarder',
        'SUCCESS:' => 'ignore',
    );

    $data = array();
    $last_key = 'ignore';

    foreach ($output as $line) {
        foreach ($mapping as $search => $key) {

            $len = strlen($search);

            if (substr($line, 0, $len) == $search) {
                $data[$key] = trim(substr($line, $len));
                $last_key = $key;

                continue 2;
            }
        }

        $data[$last_key] .= "\n" . $line;
    }

    unset($data['ignore']);

    return $data;
}

/*
 * Read driver function.
 *
 * @param array $data the array of data to get and set.
 *
 * @return integer the status code.
 */

function vacation_read(array &$data)
{
	$rcmail = rcmail::get_instance();

    $email = escapeshellcmd($data['email']);

    $cmd = "sudo /opt/psa/bin/autoresponder -i -mail $email";
    exec($cmd . ' 2>&1', $output, $returnvalue);

    if ($returnvalue !== 0)
    {
        $stroutput = implode(' ', $output);
        raise_error(array(
            'code' => 600,
            'type' => 'php',
            'file' => __FILE__, 'line' => __LINE__,
            'message' => "Vacation plugin: Unable to execute $cmd ($stroutput, $returnvalue)"
            ), true, false);
        return PLUGIN_ERROR_CONNECT;
    }

    $result = parse_plesk_output($output);

    $data['vacation_enable'] = $result['enable'] == 'true';
    $data['vacation_subject'] = $result['subject'];
    $data['vacation_message'] = $result['message'];
    $data['vacation_forwarder'] = $result['forwarder'];

/*
    Fields not currently used by Plesk:
	$data['vacation_start']
	$data['vacation_end']
	$data['vacation_keepcopyininbox']
*/

	return PLUGIN_SUCCESS;
}

/*
 * Write driver function.
 *
 * @param array $data the array of data to get and set.
 *
 * @return integer the status code.
 */
function vacation_write(array &$data)
{
	$rcmail = rcmail::get_instance();

    $email = escapeshellcmd($data['email']);
    if ($data['vacation_enable'])
        $status = "true";
    else
        $status = "false";
    $subject = escapeshellcmd($data['vacation_subject']);
    if ($rcmail->config->get('vacation_gui_vacationmessage_html'))
    {
        $format = "html";
        $text = str_replace("'", "&#39;", $data['vacation_message']);
    }
    else
    {
        $format = "plain";
        $text = escapeshellcmd($data['vacation_message']);
    }
    $redirect = escapeshellcmd($data['vacation_forwarder']);

    $cmd = sprintf("sudo /opt/psa/bin/autoresponder -u -mail %s -status %s -subject '%s' -text '%s' -format %s -redirect '%s'",
        $email, $status, $subject, $text, $format, $redirect);
    exec($cmd . ' 2>&1', $output, $returnvalue);

    if ($returnvalue == 0) {
        return PLUGIN_SUCCESS;
    }
    else
    {
        $stroutput = implode(' ', $output);
        raise_error(array(
            'code' => 600,
            'type' => 'php',
            'file' => __FILE__, 'line' => __LINE__,
            'message' => "Vacation plugin: Unable to execute $cmd ($stroutput, $returnvalue)"
            ), true, false);
    }

    return PLUGIN_ERROR_PROCESS;
}
