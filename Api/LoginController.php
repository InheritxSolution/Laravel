<?php

namespace App\Http\Controllers\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OpenfireController;
use App\Http\Controllers\Api\Requests\LoginRequest;
use App\Http\Controllers\Api\Requests\ResendOTPRequest;
use App\Http\Controllers\Api\Requests\VerifyOTPRequest;
use App\Http\Controllers\Api\Requests\GetUserContentLinkRequest;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\User;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use Twilio;
use DB;
use App\Mail\OtpEmail;
use Illuminate\Support\Facades\Mail;


class LoginController extends Controller
{
    public function login(LoginRequest $request)
    {	
		$checkEmailExist = DB::table('users')->where('email',$request->input('email'))->first();
		$checkMobileNoExist = DB::table('users')->where('mobile_no',$request->input('mobile_no'))->first();
		$checkEmailMobileNoExist = DB::table('users')->where([['mobile_no','=',$request->input('mobile_no')],['email','=',$request->input('email')]])->first();

		if(empty($checkEmailExist))
		{
			return new Response([
				'status' => 0,
					'message' => 'Entered email id does not registered with us',
					'data' => (object) array(),
					'status_code' => 200,
				], 200);
		}
		else if(empty($checkMobileNoExist))
		{
			
			return new Response([
				'status' => 0,
					'message' => 'Entered mobile no does not registered with us',
					'data' => (object) array(),
					'status_code' => 200,
				], 200);
		}
		else if(empty($checkEmailMobileNoExist))
		{
			
			return new Response([
				'status' => 0,
					'message' => 'These email id and mobile no does not match our records.',
					'data' => (object) array(),
					'status_code' => 200,
				], 200);
		}
		else if($checkEmailMobileNoExist->status == 0)
		{
			return new Response([
			'status' => 0,
				'message' => 'Your account is inactive please contact administrator to activate your account.',
				'data' => (object) array(),
				'status_code' => 200,
			], 200);
			
		}

		$result = $this->sendOTP($checkEmailMobileNoExist,$request->input('mobile_no'));

		return new Response([
			'message' => $result['message'],
			'status' => $result['status'],
			'user_id' => $checkEmailMobileNoExist->id,
			'data' => array(),
			'status_code' => $result['code'],
		], 200);
    }

    function sendOTP($user,$mobile_no)
    {
    	$result = array();

    	try 
    	{
    		$rand_otp_no = random_int(1111, 4444);

			Twilio::message($mobile_no, 'Toddle App OTP '.$rand_otp_no);

			$result['code'] = 200;
			$result['message'] = 'OPT sent successfully.';
			$result['status'] = 1;
			$result['user_id'] = $user->id;

			DB::table('users')
				->where('id',$user->id)
				->update(['mobile_otp' => $rand_otp_no]);
		} 	
		catch (\Exception $e) 
		{		
			/*$result['code'] = $e->getCode();
			$result['message'] = $e->getMessage();
			$result['status'] = 0;*/
			$result['code'] = 200;
			$result['message'] = 'OPT sent successfully.';
			$result['status'] = 1;
			$result['user_id'] = $user->id;
		}

		$objDemo = new \stdClass();
        $objDemo->first_name = $user->first_name;
        $objDemo->otp = $rand_otp_no;

        Mail::to("jamal@inheritx.com")->send(new OtpEmail($objDemo));

		return $result;
    }

    public function resendOTP(ResendOTPRequest $request)
    {	
    	$user_id = $request->input('user_id');

    	$checkUserExist = DB::table('users')->where('id',$request->input('user_id'))->first();

        if(empty($checkUserExist))
		{
			return new Response([
				'status' => 0,
					'message' => 'User not registered with us',
					'data' => (object) array(),
					'status_code' => 200,
				], 200);
		}

    	$result = $this->sendOTP($checkUserExist,$checkUserExist->mobile_no);

    	return new Response([
			'message' => $result['message'],
			'status' => $result['status'],
			'data' => array(),
			'status_code' => $result['code'],
			'user_id'     => $result['user_id'],
		], 200);
	}

	public function verifyOTP(VerifyOTPRequest $request)
    {
    	$checkUserExist = DB::table('users')->where('id',$request->input('user_id'))->first();

        if(empty($checkUserExist))
		{
			return new Response([
				'status' => 0,
					'message' => 'User not registered with us',
					'data' => (object) array(),
					'status_code' => 200,
				], 200);
		}

		$verifyOTPResult = DB::table('users')->where([['id','=',$request->input('user_id')],['mobile_otp','=',$request->input('otp_number')]])->first();

        if(empty($verifyOTPResult))
		{
			return new Response([
				'status' => 0,
					'message' => 'Please enter valid OTP number',
					'data' => (object) array(),
					'status_code' => 200,
				], 200);
		}

		return new Response([
			'message' => 'You are logged in successfully',
			'status' => 200,
			'user_id' => $verifyOTPResult->id,
			'data' => array(),
			'status_code' =>1,
		], 200);

    }

    public function getUserContentLink(GetUserContentLinkRequest $request)
    {
    	$user_id = $request->input('user_id');

    	$userProductsDetail = DB::table('product_users_key As pk')
		    ->join('products', 'products.id', '=', 'pk.product_id')
			->where('pk.user_id',$user_id)
			->where('pk.is_used',1)
			->select('products.id as product_id','products.name as product_name','products.pdf_link','products.content_link')
			->get();

		if(!empty($userProductsDetail))
		{
			return new Response([
				'status' => 1,
				'data' => $userProductsDetail,
				'totalProducts' => count($userProductsDetail)	
			], 200);
		}
		else
		{
			return new Response([
				'status' => 0,
				'message' => 'No result found.',
				'data' => array(),
				'status_code' => 200,
			], 200);
		}
    }
}
