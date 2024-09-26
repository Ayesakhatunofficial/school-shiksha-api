<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\StudentModel;

use CodeIgniter\I18n\Time;
use DateTime;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Auth extends BaseController
{
    use ResponseTrait;

    public function register()
    {
        $rules = [
            'name' => 'required',
            'mobile' => [
                'required',
                static function ($value, $data, &$error, $field) {
                    $db = db_connect();

                    $result = $db->query("SELECT * FROM tbl_users WHERE mobile = ?", [$value])->getRow();

                    if (!is_null($result)) {
                        $error = $value . ' already exist';
                        return false;
                    }

                    return true;
                }
            ],
            'email' => [
                'required',
                static function ($value, $data, &$error, $field) {
                    $db = db_connect();

                    $result = $db->query("SELECT * FROM tbl_users WHERE email = ?", [$value])->getRow();

                    if (!is_null($result) || !empty($result)) {
                        $error = $value . ' already exist';
                        return false;
                    }

                    return true;
                }
            ],
            'father_name' => 'required',
            'date_of_birth' => 'required',
            // 'aadhar_number' => 'required',
            'gender' => 'required',
            // 'nationality' => 'required',
            // 'religion' => 'required',
            'pincode' => 'required',
            'police_station' => 'required',
            'district_id' => [
                'required',
                static function ($value, $data, &$error, $field) {

                    if ($value != '') {
                        $db = db_connect();

                        $result = $db->query("SELECT * FROM tbl_districts WHERE id = ?", [$value])->getRow();

                        if (is_null($result) || empty($result)) {
                            $error = $value . ' invalid District Id';
                            return false;
                        }

                        return true;
                    } else {
                        return true;
                    }
                }
            ],
            'class_id' => [
                'required',
                static function ($value, $data, &$error, $field) {

                    if ($value != '') {
                        $db = db_connect();

                        $result = $db->query("SELECT * FROM tbl_classes WHERE id = ?", [$value])->getRow();

                        if (is_null($result) || empty($result)) {
                            $error = $value . ' invalid Class Id';
                            return false;
                        }

                        return true;
                    } else {
                        return true;
                    }
                }
            ],
            'plan_id' => [
                'required',
                static function ($value, $data, &$error, $field) {

                    if ($value != '') {
                        $db = db_connect();

                        $result = $db->query("SELECT * FROM tbl_plans WHERE id = ?", [$value])->getRow();

                        if (is_null($result) || empty($result)) {
                            $error = $value . ' invalid plan id';
                            return false;
                        }

                        return true;
                    } else {
                        return true;
                    }
                }
            ],
            'address' => 'required',
            'whatsapp_number' => 'required',
            'password' => 'required',
            'referral_code' => [
                'permit_empty',
                static function ($value, $data, &$error, $field) {

                    if ($value != '') {
                        $db = db_connect();

                        $result = $db->query("SELECT * FROM tbl_users WHERE username = ?", [$value])->getRow();

                        if (is_null($result) || empty($result)) {
                            $error = $value . ' invalid referral code';
                            return false;
                        }

                        return true;
                    } else {
                        return true;
                    }
                }
            ]
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $model = new StudentModel();

        $data = [
            'name' => $this->request->getVar('name'),
            'mobile' => $this->request->getVar('mobile'),
            'email' => $this->request->getVar('email'),
            'address' => $this->request->getVar('address'),
            'gender' => $this->request->getVar('gender'),
            'pincode' => $this->request->getVar('pincode'),
            'district_id' => $this->request->getVar('district_id'),
            'police_station' => $this->request->getVar('police_station'),
            'date_of_birth' => $this->request->getVar('date_of_birth'),
            'father_name' => $this->request->getVar('father_name'),
            'class_id' => $this->request->getVar('class_id'),
            'plan_id' => $this->request->getVar('plan_id'),
            // 'nationality' => $this->request->getVar('nationality'),
            // 'religion' => $this->request->getVar('religion'),
            'whatsapp_number' => $this->request->getVar('whatsapp_number'),
            'password' => md5($this->request->getVar('password'))
        ];

        if ($this->request->getVar('referral_code')) {
            $referral_code = $this->request->getVar('referral_code');

            $agent = $model->getAgent($referral_code);

            $data['affilate_agent_id'] = $agent->id;
        }

        $result = $model->registerUser($data);

        if ($result && is_object($result)) {

            return $this->respond([
                'status' => true,
                'message' => 'Success',
                'data' => [
                    'order_id' =>  $result->client_orderid,
                    'upi_link' => $result->upi_intent->upi_link,
                    'txn_amount' => $result->qr_data->txn_amount
                ]
            ]);
        } else if (!$result) {

            return $this->fail([
                'status' => false,
                'message' => 'Student registration not successful'
            ], STATUS_SERVER_ERROR);
        } else {

            return $this->respond([
                'status' => true,
                'message' => 'Student registration successful'
            ]);
        }
    }

    public function txnStatus()
    {
        $rules = [
            'order_id' => [
                'required',
                static function ($value, $data, &$error, $field) {

                    if ($value != '') {
                        $db = db_connect();

                        $result = $db->query("SELECT * FROM tbl_orders WHERE order_id = ?", [$value])->getRow();

                        if (is_null($result) || empty($result)) {
                            $error = $value . ' invalid order id';
                            return false;
                        }

                        return true;
                    } else {
                        return true;
                    }
                }
            ]
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $model = new StudentModel();

        $arr = [
            'status' => 'PENDING',
            'message' => 'payment pending'
        ];

        $order_id = $this->request->getVar('order_id');

        $curl = curl_init();

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => getenv('PAYMENT_BASE_URL') . 'check_order_status',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array('client_orderid' => $order_id),
            )
        );

        $response = curl_exec($curl);

        curl_close($curl);


        $response = json_decode($response);

        if ($response->data->status == 'TXN_SUCCESS') {

            $result = $model->addStudentData($order_id);

            if ($result) {

                $arr = [
                    'status' => 'SUCCESS',
                    'message' => 'Payment done and student register successfully'
                ];
            } else {
                return $this->fail([
                    'status' => false,
                    'message' => 'Student registration not successful'
                ], STATUS_SERVER_ERROR);
            }
        }

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $arr
        ]);
    }

    public function login()
    {
        $rules = [
            'username' => [
                'required',
                static function ($value, $data, &$error, $field) {
                    $db = db_connect();

                    $result = $db->query("SELECT * FROM tbl_users WHERE mobile = ?  OR username = ?", [$value, $value])->getRow();

                    if (is_null($result)) {
                        $error = $value . " Doesn't exist";
                        return false;
                    }

                    return true;
                }
            ],

            'password' => [
                'required',
                static function ($value, $data, &$error, $field) {
                    $db = db_connect();

                    $user = $db->query("SELECT * FROM tbl_users WHERE mobile = ? OR username = ? ", [$data['username'], $data['username']])->getRow();

                    if (!is_null($user)) {
                        $password = md5($value);
                        if ($user->password != $password) {
                            $error = $value . " Password does not match";
                            return false;
                        }
                    }

                    return true;
                }
            ]
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $username = $this->request->getVar('username');

        $model = new StudentModel();
        $user = $model->getUser($username);

        $auth_token = generateAuthToken($user);

        return $this->respond([
            'status' => true,
            'data' => $auth_token,
            'message' => 'User login successful'
        ]);
    }

    public function forgetPassword()
    {
        $rules = [
            'email' => [
                'required',
                static function ($value, $data, &$error, $field) {
                    $db = db_connect();
                    $result = $db->query("SELECT * FROM tbl_users WHERE email = ? ", [$value])->getRow();

                    if (is_null($result)) {
                        $error = $value . " Doesn't exist";
                        return false;
                    }

                    return true;
                }
            ]
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $user_email = $this->request->getVar('email');

        $model = new StudentModel();

        $user = $model->getStudent($user_email);

        $n = 4;

        $otp = generateNumericOTP($n);

        $otp_data = [
            'reset_otp' => $otp,
            'otp_valid_till' => date('Y-m-d H:i:s', time() + (15 * 60))
        ];

        if ($model->updateData($otp_data, $user_email)) {

            $mail = new PHPMailer(true);

            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            $mail->isSMTP();
            $mail->Host       = getsetting('smtp_host');
            $mail->SMTPAuth   = true;
            $mail->Username   = getsetting('smtp_username');
            $mail->Password   = getsetting('smtp_pass');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = getsetting('smtp_port');

            $mail->setFrom('noreply@ehostingguru.com', 'School Shiksharthi');
            $mail->addAddress($user_email, $user->name);

            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password';
            $mail->Body    = '<b style="color: blue; font-size: 15px;">' . $otp . '</b> is your OTP  for Reset Password. Please do not share this OTP to anyone.';

            if ($mail->send()) {
                return $this->respond([
                    'status' => true,
                    'message' => 'OTP send to email successfully.'
                ]);
            } else {
                return $this->fail([
                    'status' => false,
                    'message' => 'Email not sent'
                ], STATUS_SERVER_ERROR);
            }
        }
    }

    public function verifyOtp()
    {
        $rules = [
            'otp' => 'required',
            'email' => [
                'required',
                static function ($value, $data, &$error, $field) {
                    $db = db_connect();

                    $result = $db->query("SELECT * FROM tbl_users WHERE email = ? ", [$value])->getRow();

                    if (!is_null($result)) {
                        if ($result->reset_otp == $data['otp']) {
                            $valid_time = $result->otp_valid_till;
                            $current_time = date('Y-m-d H:i:s');

                            if ($current_time >= $valid_time) {
                                $error = $data['otp'] . " Time Expired";
                                return false;
                            }
                        } else {
                            $error = $data['otp'] . " Invalid";
                            return false;
                        }
                    }

                    return true;
                }
            ]
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        return $this->respond([
            'status' => true,
            'message' => 'OTP Verified'
        ]);
    }

    public function resetPassword()
    {
        $rules = [
            'email' => [
                'required',
                static function ($value, $data, &$error, $field) {
                    $db = db_connect();
                    $result = $db->query("SELECT * FROM tbl_users WHERE email = ? ", [$value])->getRow();

                    if (is_null($result)) {
                        $error = $value . " Doesn't exist";
                        return false;
                    }

                    return true;
                }
            ],
            'new_password' => 'required',
            'confirm_password' => 'required|matches[new_password]'
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $model = new StudentModel();

        $email = $this->request->getVar('email');
        $new_password = $this->request->getVar('new_password');

        $data = [
            'password' => md5($new_password)
        ];

        $update = $model->updateData($data, $email);

        if ($update) {
            return $this->respond([
                'status' => true,
                'message' => 'Password Update Successfully.'
            ]);
        } else {
            return $this->fail([
                'status' => false,
                'message' => 'Password Not Updated'
            ], STATUS_SERVER_ERROR);
        }
    }

    public function changePassword()
    {
        $rules = [
            'mobile' => [
                'required',
                static function ($value, $data, &$error, $field) {
                    $db = db_connect();

                    $result = $db->query("SELECT * FROM tbl_users WHERE mobile = ? ", [$value])->getRow();

                    if (is_null($result)) {
                        $error = $value . " Doesn't exist";
                        return false;
                    }

                    return true;
                }
            ],

            'current_password' => [
                'required',
                static function ($value, $data, &$error, $field) {
                    $db = db_connect();

                    $result = $db->query("SELECT * FROM tbl_users WHERE mobile = ? ", [$data['mobile']])->getRow();

                    if (!is_null($result)) {
                        $password = md5($value);
                        if ($password != $result->password) {
                            $error = $value . " Password doesn't match";
                            return false;
                        }
                    }

                    return true;
                }
            ],

            'new_password' => 'required',
            'confirm_password' => 'required|matches[new_password]'
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $model = new StudentModel();

        $mobile = $this->request->getVar('mobile');
        $new_password = $this->request->getVar('new_password');

        $result = $model->updatePassword($mobile, $new_password);

        if ($result) {
            return $this->respond([
                'status' => true,
                'message' => 'Password reset successfully.'
            ]);
        } else {
            return $this->fail([
                'status' => false,
                'message' => 'Password Not Updated'
            ], STATUS_SERVER_ERROR);
        }
    }
}
