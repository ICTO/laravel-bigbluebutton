<?php namespace Icto\Bigbluebutton;
/*

BigBlueButton open source conferencing system - http://www.bigbluebutton.org/

Copyright (c) 2012 BigBlueButton Inc. and by respective authors (see below).

This program is free software; you can redistribute it and/or modify it under the
terms of the GNU Lesser General Public License as published by the Free Software
Foundation; either version 3.0 of the License, or (at your option) any later
version.

BigBlueButton is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License along
with BigBlueButton; if not, see <http://www.gnu.org/licenses/>.

Versions:
   1.0  --  Initial version written by DJP
                   (email: djp [a t ]  architectes DOT .org)
   1.1  --  Updated by Omar Shammas and Sebastian Schneider
                    (email : omar DOT shammas [a t ] g m ail DOT com)
                    (email : seb DOT sschneider [ a t ] g m ail DOT com)
   1.2  --  Updated by Omar Shammas
                    (email : omar DOT shammas [a t ] g m ail DOT com)
   1.3  --  Refactored by Peter Mentzer
 					(email : peter@petermentzerdesign.com)
					- This update will BREAK your external existing code if
					  you've used the previous versions <= 1.2 already so:
						-- update your external code to use new method names if needed
						-- update your external code to pass new parameters to methods
					- Working example of joinIfRunning.php now included
					- Added support for BBB 0.8b recordings
					- Now using Zend coding, naming and style conventions
					- Refactored methods to accept standardized parameters & match BBB API structure
					    -- See included samples for usage examples
   1.4  --  Updated by xaker1
                    (email : admin [a t ] xaker1 DOT ru)

   1.5 -- Refactored by Kristof Keppens for use in Laravel
*/

class BigbluebuttonApi
{

    /**
     * @var
     */
    var $config;

    /**
     * @param $config
     */
    function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @param $method
     * @param $urlParams
     * @return string
     */
    private function getChecksum($method, $urlParams = '')
    {
        $checksum = '&checksum=' . sha1('create' . $urlParams . $this->config['bbb_security_salt']);
        return $checksum;
    }

    /**
     * A helper function to process XML responses from BBB
     *
     * @param  string $url
     * @param  string $xml
     * @return SimpleXMLElement
     * @throws Exception
     */
    private function processXmlResponse($url, $xml = '')
    {
        if (extension_loaded('curl')) {
            $ch = curl_init() or die (curl_error());
            $timeout = 10;
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            if (!empty($xml)) {
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-type: application/xml',
                    'Content-length: ' . strlen($xml)
                ));
            }
            $data = curl_exec($ch);
            curl_close($ch);

            if ($data)
                return (new \SimpleXMLElement($data));
            else
                return false;
        }
        if (!empty($xml))
            throw new \Exception('Set xml, but curl is not installed.');

        return (simplexml_load_file($url));
    }

    /**
     *  __________________ BBB ADMINISTRATION METHODS _________________
     *
     * The methods in the following section support the following categories of the BBB API:
     * - create
     * - join
     * - end
     */

    /**
     * Get url to create a new meeting
     *
     * @param string $meetingID Unique meeting identifier
     * @param  Array $params
     * @return string
     */
    public function getCreateMeetingUrl($meetingID, $params)
    {
        // Set up the basic creation URL:
        $creationUrl = $this->config['bbb_server_url'] . "api/create?";

        $params['meetingID'] = $meetingID;

        $urlParams = http_build_query($params);
        $checksum = $this->getChecksum('create', $urlParams);
        $url = $creationUrl . $urlParams . $checksum;

        // Return the complete URL:
        return $url;
    }

    /**
     * [createMeetingWithXmlResponseArray description]
     *
     *
     * @param $meetingID
     * @param $creationParams
     * @param  string $xml Use to pass additional xml to BBB server. Example, use to Preupload Slides. See API docs.
     * @return array|null [type]                 [description]
     * @throws Exception
     * @internal param $ [type] $creationParams $creationParams = array(
     *        'name' => 'Meeting Name',    -- A name for the meeting (or username)
     *        'meetingId' => '1234',        -- A unique id for the meeting
     *        'attendeePw' => 'ap',        -- Set to 'ap' and use 'ap' to join = no user pass required.
     *        'moderatorPw' => 'mp',        -- Set to 'mp' and use 'mp' to join = no user pass required.
     *        'welcomeMsg' => '',        -- ''= use default. Change to customize.
     *        'dialNumber' => '',        -- The main number to call into. Optional.
     *        'voiceBridge' => '12345',    -- 5 digit PIN to join voice conference.  Required.
     *        'webVoice' => '',            -- Alphanumeric to join voice. Optional.
     *        'logoutUrl' => '',            -- Default in bigbluebutton.properties. Optional.
     *        'maxParticipants' => '-1',    -- Optional. -1 = unlimitted. Not supported in BBB. [number]
     *        'record' => 'false',        -- New. 'true' will tell BBB to record the meeting.
     *        'duration' => '0',            -- Default = 0 which means no set duration in minutes. [number]
     *        'meta_category' => '',        -- Use to pass additional info to BBB server. See API docs to enable.
     *        );
     */
    public function createMeeting($meetingID, $creationParams, $xml = '')
    {
        $xml = $this->processXmlResponse($this->getCreateMeetingURL($meetingID, $creationParams), $xml);

        if ($xml) {
            if ($xml->meetingID)
                return array(
                    'returncode' => $xml->returncode,
                    'message' => $xml->message,
                    'messageKey' => $xml->messageKey,
                    'meetingId' => $xml->meetingID,
                    'attendeePw' => $xml->attendeePW,
                    'moderatorPw' => $xml->moderatorPW,
                    'hasBeenForciblyEnded' => $xml->hasBeenForciblyEnded,
                    'createTime' => $xml->createTime
                );
            else
                return array(
                    'returncode' => $xml->returncode,
                    'message' => $xml->message,
                    'messageKey' => $xml->messageKey
                );
        } else {
            return null;
        }
    }

    /**
     * Get the url to join a meeting.
     *
     * @param string $meetingID unique identifier for the meeting
     * @param string $username username with which the user is represented in the webconference.
     * @param string $password password for moderator or attendee role
     * @param Array $joinParams extra optional parameters ['createTime', 'userID', 'webVoiceConf']
     * @return string $url
     */
    public function getJoinMeetingURL($meetingID, $username, $password, $joinParams)
    {
        // Establish the basic join URL:
        $creationUrl = $this->config['bbb_server_url'] . "api/join?";

        $params['meetingID'] = $meetingID;
        $params['fullName'] = $username;
        $params['password'] = $password;

        $urlParams = http_build_query($params);
        $checksum = $this->getChecksum('join', $urlParams);
        $url = $creationUrl . $urlParams . $checksum;

        // Return the complete URL:
        return $url;
    }

    /**
     * Get url to end a meeting.
     *
     * @param string $meetingID unique meeting identifier
     * @param string $password moderator password for the meeting
     * @return string $url
     */
    public function getEndMeetingURL($meetingID, $password)
    {
        $endUrl = $this->config['bbb_server_url'] . "api/end?";

        $params['meetingID'] = $meetingID;
        $params['password'] = $password;

        $urlParams = http_build_query($params);
        $checksum = $this->getChecksum('end', $urlParams);

        $url = $endUrl . $urlParams . $checksum;

        // Return the complete URL:
        return $url;
    }

    /**
     * End a webmeeting and return the XML response
     *
     * @param string $meetingID unique meeting identifier
     * @param string $password moderator password
     * @return array|null
     * @throws Exception
     */
    public function endMeeting($meetingID, $password)
    {
        $xml = $this->processXmlResponse($this->getEndMeetingURL($meetingID, $password));
        if ($xml) {
            return array(
                'returncode' => $xml->returncode,
                'message' => $xml->message,
                'messageKey' => $xml->messageKey
            );
        } else {
            return null;
        }

    }

    /** __________________ BBB MONITORING METHODS _________________
     * The methods in the following section support the following categories of the BBB API:
     * - isMeetingRunning
     * - getMeetings
     * - getMeetingInfo
     */

    /**
     * @param $meetingId unique meeting identifier
     * @return string
     */
    public function getIsMeetingRunningUrl($meetingId)
    {
        $runningUrl = $this->config['bbb_server_url'] . "api/isMeetingRunning?";
        $params['meetingID'] = $meetingId;
        $urlParams = http_build_query($params);
        $checksum = $this->getChecksum('isMeetingRunning', $urlParams);

        $url = $runningUrl . $urlParams . $checksum;
        return $url;
    }

    /**
     * @param string $meetingId unique meeting identifier
     * @return array|null
     * @throws Exception
     */
    public function isMeetingRunning($meetingID)
    {
        $xml = $this->processXmlResponse($this->getIsMeetingRunningUrl($meetingID));
        if ($xml->running == 'true')
            return true;
        else
            return false;
    }

    /**
     * Create the getmeetings url
     *
     * @return string
     */
    public function getGetMeetingsUrl()
    {
        $checksum = $this->getChecksum('getMeetings');
        $getMeetingsUrl = $this->config['bbb_server_url'] . "api/getMeetings?" . $checksum;
        return $getMeetingsUrl;
    }

    /**
     * @return array|null
     * @throws Exception
     */
    public function getMeetings()
    {
        $xml = $this->_processXmlResponse($this->getGetMeetingsUrl());
        if ($xml) {
            // If we don't get a success code, stop processing and return just the returncode:
            if ($xml->returncode != 'SUCCESS') {
                $result = array(
                    'returncode' => $xml->returncode
                );
                return $result;
            } elseif ($xml->messageKey == 'noMeetings') {
                /* No meetings on server, so return just this info: */
                $result = array(
                    'returncode' => $xml->returncode,
                    'messageKey' => $xml->messageKey,
                    'message' => $xml->message
                );
                return $result;
            } else {
                // In this case, we have success and meetings. First return general response:
                $result = array(
                    'returncode' => $xml->returncode,
                    'messageKey' => $xml->messageKey,
                    'message' => $xml->message
                );
                // Then interate through meeting results and return them as part of the array:
                foreach ($xml->meetings->meeting as $m) {
                    $result[] = array(
                        'meetingId' => $m->meetingID,
                        'meetingName' => $m->meetingName,
                        'createTime' => $m->createTime,
                        'attendeePw' => $m->attendeePW,
                        'moderatorPw' => $m->moderatorPW,
                        'hasBeenForciblyEnded' => $m->hasBeenForciblyEnded,
                        'running' => $m->running
                    );
                }
                return $result;
            }
        } else {
            return null;
        }

    }

    /**
     * Get the url for the getmeetinginfo api call
     *
     * @param string $meetingID unique meeting identifier
     * @param string $password moderator password
     * @return string
     */
    public function getMeetingInfoUrl($meetingID, $password)
    {
        $infoUrl = $this->config['bbb_server_url'] . "api/getMeetingInfo?";

        $params['meetingID'] = $meetingID;
        $params['password'] = $password;

        $urlParams = http_build_query($params);
        $checksum = $this->getChecksum('getMeetignInfo', $urlParams);

        $url = $runningUrl . $urlParams . $checksum;

        return $url;
    }

    /**
     * @param $infoParams
     * @return array|null
     * @throws Exception
     */
    public function getMeetingInfo($meetingID, $password)
    {
        $xml = $this->processXmlResponse($this->getMeetingInfoUrl($meetingID, $password));
        if ($xml) {
            // If we don't get a success code or messageKey, find out why:
            if (($xml->returncode != 'SUCCESS') || ($xml->messageKey == null)) {
                $result = array(
                    'returncode' => $xml->returncode,
                    'messageKey' => $xml->messageKey,
                    'message' => $xml->message
                );
                return $result;
            } else {
                // In this case, we have success and meeting info:
                $result = array(
                    'returncode' => $xml->returncode,
                    'meetingName' => $xml->meetingName,
                    'meetingId' => $xml->meetingID,
                    'createTime' => $xml->createTime,
                    'voiceBridge' => $xml->voiceBridge,
                    'attendeePw' => $xml->attendeePW,
                    'moderatorPw' => $xml->moderatorPW,
                    'running' => $xml->running,
                    'recording' => $xml->recording,
                    'hasBeenForciblyEnded' => $xml->hasBeenForciblyEnded,
                    'startTime' => $xml->startTime,
                    'endTime' => $xml->endTime,
                    'participantCount' => $xml->participantCount,
                    'maxUsers' => $xml->maxUsers,
                    'moderatorCount' => $xml->moderatorCount,
                );
                // Then interate through attendee results and return them as part of the array:
                foreach ($xml->attendees->attendee as $a) {
                    $result[] = array(
                        'userId' => $a->userID,
                        'fullName' => $a->fullName,
                        'role' => $a->role
                    );
                }
                return $result;
            }
        } else {
            return null;
        }

    }

    /** __________________ BBB RECORDING METHODS _________________
     * The methods in the following section support the following categories of the BBB API:
     * - getRecordings
     * - publishRecordings
     * - deleteRecordings
     */

    /**
     * Create the url to retrieve a recording
     *
     * @param string $meetingID unique meeting identifier
     * @return string
     */
    public function getRecordingsUrl($meetingID)
    {
        $recordingsUrl = $this->config['bbb_server_url'] . "api/getRecordings?";
        $params['meetingID'] = $meetingID;

        $urlParams = http_build_query($params);
        $checksum = $this->getChecksum('getRecordings', $urlParams);
        $url = $recordingsUrl . $urlParams . $checksum;

        return $url;
    }

    /**
     * @param $recordingParams
     * @return array|null
     * @throws Exception
     */
    public function getRecordings($meetingID)
    {
        $xml = $this->processXmlResponse($this->getRecordingsUrl($meetingID));
        if ($xml) {
            // If we don't get a success code or messageKey, find out why:
            if (($xml->returncode != 'SUCCESS') || ($xml->messageKey == null)) {
                $result = array(
                    'returncode' => $xml->returncode,
                    'messageKey' => $xml->messageKey,
                    'message' => $xml->message
                );
                return $result;
            } else {
                // In this case, we have success and recording info:
                $result = array(
                    'returncode' => $xml->returncode,
                    'messageKey' => $xml->messageKey,
                    'message' => $xml->message
                );

                foreach ($xml->recordings->recording as $r) {
                    $result[] = array(
                        'recordId' => $r->recordID,
                        'meetingId' => $r->meetingID,
                        'name' => $r->name,
                        'published' => $r->published,
                        'startTime' => $r->startTime,
                        'endTime' => $r->endTime,
                        'playbackFormatType' => $r->playback->format->type,
                        'playbackFormatUrl' => $r->playback->format->url,
                        'playbackFormatLength' => $r->playback->format->length,
                        'metadataTitle' => $r->metadata->title,
                        'metadataSubject' => $r->metadata->subject,
                        'metadataDescription' => $r->metadata->description,
                        'metadataCreator' => $r->metadata->creator,
                        'metadataContributor' => $r->metadata->contributor,
                        'metadataLanguage' => $r->metadata->language,
                    );
                }
                return $result;
            }
        } else {
            return null;
        }
    }

    /**
     * Gets the url to publish/unpublish a recording
     *
     * @param string recordID recording id or comma separated list of recording ids
     * @param boolean $publish publish the recording
     * @return string
     */
    public function getPublishRecordingsUrl($recordID, $publish)
    {
        $recordingsUrl = $this->config['bbb_server_url'] . "api/publishRecordings?";

        $params['recordID'] = $recordID;
        $params['publish'] = ($publish) ? 'true' : 'false';

        $urlParams = http_build_query($params);
        $checksum = $this->getChecksum('publishRecordings', $urlParams);

        $url = $recordingsUrl . $urlParams . $checksum;

        return $url;
    }

    /**
     * Publish/Unpublish the recording(s) and return the XML response
     *
     * @param string $recordID unique record id or comma separated list of record ids
     * @param boolean $publish whether or not to publish the recordings
     * @return array|null
     * @throws Exception
     */
    public function publishRecordings($recordID, $publish)
    {
        $xml = $this->processXmlResponse($this->getPublishRecordingsUrl($recordID, $publish));
        if ($xml) {
            return array(
                'returncode' => $xml->returncode,
                'published' => $xml->published
            );
        } else {
            return null;
        }
    }

    /**
     * Creates the url to delete a recording
     *
     * @param string $recordID unique record id
     * @return string
     */
    public function getDeleteRecordingsUrl($recordID)
    {
        $recordingsUrl = $this->config['bbb_server_url'] . "api/deleteRecordings?";
        $params['recordID'] = $recordID;

        $urlParams = http_build_query($params);
        $checksum = $this->getChecksum('deleteRecordings', $urlParams);

        $url = $recordingsUrl . $urlParams . $checksum;

        return $url;
    }

    /**
     * Deletes a recording and returns the XML response
     *
     * @param string $recordID unique record id
     * @return array|null
     * @throws Exception
     */
    public function deleteRecordings($recordID)
    {
        $xml = $this->processXmlResponse($this->getDeleteRecordingsUrl($recordID));
        if ($xml) {
            return array(
                'returncode' => $xml->returncode,
                'deleted' => $xml->deleted    // -- Returns true/false.
            );
        } else {
            return null;
        }
    }
}
