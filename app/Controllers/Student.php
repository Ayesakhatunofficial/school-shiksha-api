<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\StudentModel;
use CodeIgniter\API\ResponseTrait;

class Student extends BaseController
{
    use ResponseTrait;

    public function getProfile()
    {
        $user = authuser();

        $model = new StudentModel();

        $data = $model->getProfileData($user->id);
        if (!is_null($data)) {
            return $this->respond([
                'status' => true,
                'message' => 'Success',
                'data' => $data,
            ]);
        }
    }

    public function editProfile()
    {
        $rules = [
            'id' => 'required',
            'name' => 'required',
            'email' => [
                'required',
                static function ($value, $data, &$error, $field) {
                    $db = db_connect();

                    $result = $db->query("SELECT * FROM tbl_users WHERE email = ? AND id != ?", [$value, $data['id']])->getRow();

                    if (!is_null($result) || !empty($result)) {
                        $error = $value . ' already exist';
                        return false;
                    }

                    return true;
                }
            ],
            'father_name' => 'required',
            'whatsapp_number' => 'required',
            'date_of_birth' => 'required',
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
            'address' => 'required',
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $data = $this->request->getVar();

        $model = new StudentModel();

        $result = $model->updateProfile($data);

        if ($result) {
            return $this->respond([
                'status' => true,
                'message' => 'Profile updated successfully.'
            ]);
        } else {
            return $this->fail([
                'status' => false,
                'message' => 'Profile Not Updated'
            ], STATUS_SERVER_ERROR);
        }
    }

    public function orderDetails()
    {
        $rules = [
            'page' => 'required|integer'
        ];

        if (!$this->validate($rules)) {
            return $this->fail([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $page = $this->request->getVar('page');

        $user = authuser();
        $model = new StudentModel();

        $result = $model->getOrderDetails($user->id, $page);
        $curr_batch = count($model->getOrderDetails($user->id, $page));
        $count = $model->getOrderDetailsCount($user->id);


        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => [
                'items' => $result,
                'current_batch' => $curr_batch,
                'total_count' => $count->total_count
            ]
        ]);
    }

    public function getNotifications()
    {
        $rules = [
            'page' => 'required|integer'
        ];

        if (!$this->validate($rules)) {
            return $this->fail([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $page = $this->request->getVar('page');

        $user = authuser();
        $model = new StudentModel();
        $result = $model->getNotifications($user->id, $page);

        $curr_batch = count($model->getNotifications($user->id, $page));
        $count = $model->getNotificationCount($user->id);

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => [
                'items' => $result,
                'current_batch' => $curr_batch,
                'total_count' => $count->total_count
            ]
        ]);
    }

    public function notificationView()
    {
        $rules = [
            'id' => [
                'required',
                static function ($value, $data, &$error, $field) {

                    if ($value != '') {
                        $db = db_connect();

                        $result = $db->query("SELECT * FROM tbl_notifications WHERE id = ?", [$value])->getRow();

                        if (is_null($result) || empty($result)) {
                            $error = $value . ' invalid Notification Id';
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
            return $this->fail([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $id = $this->request->getVar('id');

        $model = new StudentModel();

        $result = $model->getNotificationById($id);

        if (!is_null($result)) {
            return $this->respond([
                'status' => true,
                'message' => 'Success',
                'data' => $result
            ]);
        }
    }

    public function getNotificationCount()
    {
        $user = authuser();
        $model = new StudentModel();

        $count = $model->getNotificationCountById($user->id);

        if (!is_null($count)) {
            return $this->respond([
                'status' => true,
                'message' => 'Success',
                'data' => $count
            ]);
        }
    }

    public function query()
    {
        $user = authuser();

        $model = new StudentModel();

        $rules = [
            'name' => 'required',
            'email' => 'required',
            'mobile' => 'required',
            'message' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $input = $this->request->getVar();

        $result = $model->addQuery($input, $user->id);

        if ($result) {
            return $this->respond([
                'status' => true,
                'message' => 'Query submitted successfully.'
            ]);
        } else {
            return $this->fail([
                'status' => false,
                'message' => 'Something went wrong!'
            ], STATUS_SERVER_ERROR);
        }
    }

    public function buyPlan()
    {
        $rules = [
            'plan_id' => [
                'required',
                static function ($value, $data, &$error, $field) {

                    if ($value != '') {
                        $db = db_connect();

                        $result = $db->query("SELECT * FROM tbl_plans WHERE id = ?", [$value])->getRow();

                        if (is_null($result) || empty($result)) {
                            $error = $value . ' invalid Plan Id';
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

        $plan_id = $this->request->getVar('plan_id');

        $model = new StudentModel();

        $plan_details = $model->getPlanDetails($plan_id);

        $amount = $plan_details->plan_amount;
        // $amount = 1;
        $txn_note = 'UPI Payment';

        $curl = curl_init();

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => getenv('PAYMENT_BASE_URL') . 'create_order',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array('token' => getenv('PAYMENT_TOKEN'), 'secret' => getenv('PAYMENT_SECRET'), 'amount' => $amount, 'txn_note' => $txn_note, 'udf1' => '', 'udf2' => ''),
            )
        );

        $response = curl_exec($curl);

        curl_close($curl);

        $order_data = json_decode($response);

        $data = [
            'order_id' => $order_data->client_orderid,
            'upi_link' => $order_data->upi_intent->upi_link,
            'txn_amount' => $order_data->qr_data->txn_amount
        ];

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function statusCheck()
    {
        $rules = [
            'order_id' => 'required',
            'plan_id' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $user = authuser();
        $stud_id = $user->id;
        $order_id = $this->request->getVar('order_id');
        $plan_id = $this->request->getVar('plan_id');

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

        $arr = [
            'status' => 'PENDING',
            'message' => 'payment pending'
        ];

        if ($response->data->status == 'TXN_SUCCESS') {
            $studentModel = new StudentModel();
            $payment_txn_id = $response->data->upi_txn_id;
            $result = $studentModel->addSubscription($plan_id, $stud_id, $payment_txn_id);
            if ($result) {
                $arr = [
                    'status' => 'SUCCESS',
                    'message' => 'payment done'
                ];
            }
        }

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $arr,
        ]);
    }

    public function formSubmit()
    {
        $rules = [
            'service_id' => [
                'required',
                static function ($value, $data, &$error, $field) {

                    if ($value != '') {
                        $db = db_connect();

                        $result = $db->query("SELECT * FROM tbl_services WHERE id = ?", [$value])->getRow();

                        if (is_null($result) || empty($result)) {
                            $error = $value . ' invalid service id';
                            return false;
                        }

                        return true;
                    } else {
                        return true;
                    }
                }
            ],
            'organization_course_id' => [
                'required',
                static function ($value, $data, &$error, $field) {

                    if ($value != '') {
                        $db = db_connect();

                        $result = $db->query("SELECT * FROM tbl_organizations_course WHERE id = ?", [$value])->getRow();

                        if (is_null($result) || empty($result)) {
                            $error = $value . ' invalid organization course id';
                            return false;
                        }

                        return true;
                    } else {
                        return true;
                    }
                }
            ],
            'enquiry_details' => 'required',
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $input = $this->request->getVar();
        $model = new StudentModel();

        $result = $model->addFormData($input);

        if ($result) {
            return $this->respond([
                'status' => true,
                'message' => 'Form Submitted successfully.'
            ]);
        } else {
            return $this->fail([
                'status' => false,
                'message' => 'Something went wrong!'
            ], STATUS_SERVER_ERROR);
        }
    }

    public function carrierGuide()
    {
        $rules = [
            'name' => 'required',
            'email' => 'required|valid_email',
            'mobile' => 'required|integer|min_length[10]|max_length[10]',
            'guardian_name' => 'required',
            'whatsapp_number' => 'required|integer|min_length[10]|max_length[10]',
            'mp_percentage' => 'required',
            'hs_percentage' => 'permit_empty',
            'stream' => 'permit_empty',
            'interest_course_name' => 'permit_empty'

        ];

        if (!$this->validate($rules)) {
            return $this->fail([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $data = $this->request->getVar();

        $model = new StudentModel();

        $result = $model->addCarrierGuide($data);

        if ($result) {
            return $this->respond([
                'status' => true,
                'message' => 'Form Submitted successfully.'
            ]);
        } else {
            return $this->fail([
                'status' => false,
                'message' => 'Something went wrong!'
            ], STATUS_SERVER_ERROR);
        }
    }

    public function externalRecords()
    {
        $rules = [
            'organization_course_id' => [
                'required',
                static function ($value, $data, &$error, $field) {

                    if ($value != '') {
                        $db = db_connect();

                        $result = $db->query("SELECT * FROM tbl_organizations_course WHERE id = ?", [$value])->getRow();

                        if (is_null($result) || empty($result)) {
                            $error = $value . ' invalid organization course id';
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
            return $this->fail([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $model = new StudentModel();
        $user = authuser();
        $organization_course_id = $this->request->getVar('organization_course_id');

        $result = $model->addExternalRecord($user->id, $organization_course_id);

        if ($result) {
            return $this->respond([
                'status' => true,
                'message' => 'Record Added successfully.'
            ]);
        } else {
            return $this->fail([
                'status' => false,
                'message' => 'Something went wrong!'
            ], STATUS_SERVER_ERROR);
        }
    }
}
