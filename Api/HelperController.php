<?php

namespace App\Http\Controllers\Api\Controllers;

use App\Http\Controllers\Controller;
use JWTAuth;
use App\User;
use DB;
use Illuminate\Http\Response;

class HelperController extends Controller
{
    public function sendNotification($from_user_id,$to_user_id,$notif_title,$type,$element_id,$read = 0,$group_id = 0)
    {
    	$addNotifArr[] = array(
    		    'from_user'            =>   $from_user_id,
	            'to_user'              =>   $to_user_id, 
	            'notification_title'   =>   $notif_title,
	            'element_id'           =>   $element_id,
	            'type'                 =>   $type,
	            'is_read'              =>   $read,
	            'created_date'         => date('Y-m-d G:i:s')
	    );
		
        if($type != 'post')
        {
            $addNotifResult = DB::table('activity_notification')->insert($addNotifArr);

            if(!$addNotifResult)
            {
                return new Response([
                        'message' => 'Something went wrong, please try again.',
                        'status' => 0,
                        'data' => (object) array(),
                    ], 200);
            }
        }
		
        $userDetail = $this->getUserDetail($to_user_id);
        $addNotifArr[0]['device_token'] = $userDetail->device_token;
        $addNotifArr[0]['device_type'] = $userDetail->device_type;
        $addNotifArr[0]['group_id'] = $group_id;

        if($userDetail->device_type == 'android')
        {
            $this->SendNotificationAndroid($addNotifArr[0]);
        }
        else
        {
            $this->SendNotificationAndroid($addNotifArr[0]);
        }
        
    }

    public function getUserDetail($user_id)
	{
		$userDetail = DB::table('users')
			->where('status',1)
			->where('id',$user_id)
			->select('id','name','device_token','device_type')
			->get()
			->first();

			return $userDetail;
	}

	public function getUserDefaultGroup($user_id)
	{
		$grpDetail = DB::table('groups')
			->where('default_group',1)
			->where('created_by',$user_id)
			->select('id','group_name','group_image')
			->get()
			->first();

		return $grpDetail;
	}

    public function updateDeviceToken($user_id,$device_token,$device_type)
    {
        $updateDeviceTokRes = DB::table('users')
                ->where('id',$user_id)
                ->update(
                         array(
                                'device_type'     =>  $device_type, 
                                'device_token'   =>   $device_token   
                         ));
    }

	public function deleteNotification($from_user,$to_user,$type)
	{
		$result = DB::table('activity_notification')->where([['to_user','=',$to_user],['from_user','=',$from_user],['type','=',$type]])->delete();
	}

    // Send push notification to members - using andorid
    public static function SendNotificationAndroid($arr) 
    {
        $RegisterKey = $arr['device_token'];
        $url = 'https://fcm.googleapis.com/fcm/send';
        // your api key SERVER API KEY
        $apiKey = config('params.push_notification.android_api_key');

        // registration ids DEVICE TOKEN, manually written
        if (is_array($RegisterKey)) {
            $registrationIDs = $RegisterKey;
        } else {
            $registrationIDs = array($RegisterKey);
        }

        $Data = array(
            'to'           => $arr['device_token'],
            'priority'     => 'high',
            'collapse_key' => $arr['type'],
            'time_to_live' => 2419200,
            "click_action" => $arr['type'],
            'data'         => [
                                "body"              => $arr['notification_title'],
                                "title"             => "RoadWarriors",
                                "action_tag"        => $arr['type'],
                                "message"           => $arr['notification_title'],
                                "ticker"            => $arr['notification_title'],
                                "user_id"           => ($arr['type'] == 'post') ? $arr['to_user']:$arr['from_user'],
                                "post_id"           => ($arr['type'] == 'post') ? $arr['element_id']:0,
                                "group_id"          => ($arr['type'] == 'group') ? $arr['element_id']:$arr['group_id'],
                                'notification_data' => array(),
                              ],
        );

        // http header
        $headers = array(
                        'Authorization: key=' . $apiKey,
                        'Content-Type: application/json'
                    );

        // curl connection
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($Data));

        $result = curl_exec($ch);

        curl_close($ch);
    }

    // Send Push Notification to Members - using iphone
    public function SendNotificationIphone($arr) 
    {
        $deviceid      = $arr['device_token'];
        $pemfielname   = url('/').'/pem_files/'.config('params.push_notification.iphone_pem_file');

        $amAPNSRequest = [
            'apns_host'         => 'ssl://gateway.push.apple.com:2195', // 'ssl://gateway.sandbox.push.apple.com:2195',
            'apsn_certificate'  => $pemfielname,
            'apns_pass_pharse'  => '',
            'ssMessage'         => $arr['notification_title'],
            'Badge'             => 0,
            'notification_type' => $arr['type'],
            'notification_data' => array(),
        ];

        $this->sendAPNS($deviceid, $amAPNSRequest);

         //$oResult = Common::sendAPNS($deviceid, $amAPNSRequest);  // Make sure you're returning $oResult from sendAPNS()
         //Common::apiLog($amAPNSRequest);
         //Common::apiLog($oResult);

        // return $oResult;
    }

    public function sendAPNS($ssDeviceToken, $amAPNSReques, $ssTags = 201) 
    {
        $ssApnsHost   = $amAPNSReques['apns_host'];
        $ssApnsCert   = $amAPNSReques['apsn_certificate'];
        $ssPassPhrase = $amAPNSReques['apns_pass_pharse'];
        $ssBadgeCount = $amAPNSReques['Badge'];
        $passphrase   = '';

          
        $ssCertifiateFilePath =  $amAPNSReques['apsn_certificate'];

         echo "string";exit();

        if (file_exists($ssCertifiateFilePath)) 
        {


          

            $ctx = stream_context_create();
            stream_context_set_option($ctx, 'ssl', 'local_cert', $ssCertifiateFilePath);
            // Open a connection to the APNS server
            $oFp = stream_socket_client($ssApnsHost, $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx);

            if ($oFp) 
            {
                try 
                {
                    // Create the payload body
                    $amBody['aps']   = array(
                        'alert'             => ($amAPNSReques['type'] != Yii::$app->params['type']['post_in_event'])?$amAPNSReques['ssMessage']:'',
                        'type'              => $amAPNSReques['type'],
                        'notification_data' => $amAPNSReques['notification_data'],
                        'badge'             => $ssBadgeCount,
                        'sound'             => ($amAPNSReques['type'] != Yii::$app->params['type']['post_in_event'])?'default':'',
                        'content-available' => "1",
                        'apns-id'           => strval($amAPNSReques['apns_id']),
                    );

                    // 20170823: Mute for removedFromEvent notification
                    // 20170908: Mute for PrivateModeOn & PrivateModeOff notifications
                    if ($amAPNSReques['type'] == "removedFromEvent" || (isset($amAPNSReques['notification_data']['message_type']) && ($amAPNSReques['notification_data']['message_type'] == 'PrivateModeOn' || $amAPNSReques['notification_data']['message_type'] == 'PrivateModeOff'))) {
                        $amBody['aps']['sound'] = '';
                        $amBody['aps']['alert'] = '';
                    }

                    //20180424: set sound for p2p chat
                    if ($amAPNSReques['type'] == "p2pChat") 
                    {
                        $amBody['aps']['sound'] = 'SoftBell.wav';
                    }

                    if ($amAPNSReques['type'] == "p2pChat" && $amAPNSReques['notification_data']['message_type']=='story_reply') 
                    {
                        $amBody['aps']['alert'] = 'story_reply';
                    }
                    // Encode the payload as JSON
                    $amEncodePayload = json_encode($amBody);

                    // Build the binary notification
                    $smEncodeMsg     = chr(0) . pack('n', 32) . pack('H*', $ssDeviceToken) . pack('n', strlen($amEncodePayload)) . $amEncodePayload;
                    // Send it to the server
                    $oResult         = fwrite($oFp, $smEncodeMsg, strlen($smEncodeMsg));
                    fclose($oFp);
                    // Common::debugLog($amEncodePayload);
                    // Common::debugLog($oResult);

                    // return json_encode($oResult);
                } 
                catch (Exception $e) 
                {
                    //echo 'Caught exception: '.  $e->getMessage(). "\n";
                }
            }
        }
    }
}
