<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\MasterModel;

class Master extends BaseController
{
    use ResponseTrait;

    public function getState()
    {

        $model = new MasterModel();

        $result = $model->getState();

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $result
        ]);
    }

    public function getDistrict()
    {
        $rules = [
            'state_id' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $state_id = $this->request->getVar('state_id');

        $model = new MasterModel();

        $result = $model->getDistrict($state_id);

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $result
        ]);
    }

    public function getBlock()
    {
        $rules = [
            'district_id' => [
                'required',
                static function ($value, $data, &$error, $field) {

                    if ($value != '') {
                        $db = db_connect();

                        $result = $db->query("SELECT * FROM tbl_districts WHERE id = ?", [$value])->getRow();

                        if (is_null($result) || empty($result)) {
                            $error = $value . ' invalid district id';
                            return false;
                        }

                        return true;
                    } else {
                        return true;
                    }
                }
            ],
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $district_id = $this->request->getVar('district_id');

        $model = new MasterModel();

        $result = $model->getBlock($district_id);

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $result
        ]);
    }

    public function getPlan()
    {
        $model = new MasterModel();

        $result = $model->getPlan();

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $result
        ]);
    }

    public function getServiceType()
    {
        $model = new MasterModel();

        $service_types = [
            [
                'short_name' => 'mp',
                'long_name' => "10th & 12th Pass Student's Benefits"
            ],
            [
                'short_name' => 'hs',
                'long_name' => "Entrance Exam & College Admission"
            ],
            [
                'short_name' => 'graduate',
                'long_name' => "Job/Govt.Training/Apprenticeship Training"
            ],
            [
                'short_name' => 'other',
                'long_name' => "Class (1-10 ) Student's Benefits"
            ]
        ];

        foreach ($service_types as $index => $type) {
            $intended_for = $type['short_name'];
            $limit = 3;

            $service_types[$index]['services'] = $model->getServices($intended_for, $limit);
            $count = $model->getServiceCount($intended_for);
            $service_types[$index]['count'] = $count->total_count;
        }

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $service_types,
        ]);
    }

    public function getServices()
    {
        $rules = [
            'service_type' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $service_type = $this->request->getVar('service_type');
        $limit = $this->request->getVar('limit');

        $model = new MasterModel();

        $results = $model->getServices($service_type, $limit);

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $results
        ]);
    }

    public function getClass()
    {
        $model = new MasterModel();

        $data = $model->getClass();

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function getCourses()
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
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $service_id = $this->request->getVar('service_id');

        $model = new MasterModel();

        $data = $model->getCourse($service_id);

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function getOrganizationCourse()
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
            'page' => 'required|integer'
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $data = [
            'search_value' => $this->request->getVar('search_value'),
            'course_id' => $this->request->getVar('course_id'),
            'state_id' => $this->request->getVar('state_id'),
            'district_id' => $this->request->getVar('district_id'),
            'block_id' => $this->request->getVar('block_id'),
            'page' => $this->request->getVar('page'),
            'service_id' => $this->request->getVar('service_id')
        ];


        $model = new MasterModel();

        $result = $model->getOrganizationCourse($data);
        $curr_batch = count($result['data']);

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $result['data'],
            'current_batch' => $curr_batch,
            'total_count' => $result['total_count']
        ]);
    }

    public function getCourseDetails()
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
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $id = $this->request->getVar('organization_course_id');

        $model = new MasterModel();

        $result = $model->getCourseDetailsById($id);

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $result
        ]);
    }

    public function getContactDetails()
    {
        $mobile = getsetting('support_phone');
        $email = getsetting('support_email');
        $whatsapp = getsetting('support_whatsapp_no');

        $result = [
            'mobile' => $mobile,
            'email' => $email,
            'whatsapp_number' => $whatsapp
        ];

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $result
        ]);
    }

    public function getOrganizationBanner()
    {
        $rules = [
            'organization_id' => [
                'required',
                static function ($value, $data, &$error, $field) {

                    if ($value != '') {
                        $db = db_connect();

                        $result = $db->query("SELECT * FROM tbl_organizations WHERE id = ?", [$value])->getRow();

                        if (is_null($result) || empty($result)) {
                            $error = $value . ' invalid organization id';
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

        $organization_id = $this->request->getVar('organization_id');

        $model = new MasterModel();
        $result = $model->OrganizationBanner($organization_id);

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $result
        ]);
    }

    public function getServiceBanner()
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
            ]
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], STATUS_VALIDATION_ERROR, 'Validation error');
        }

        $service_id = $this->request->getVar('service_id');

        $model = new MasterModel();
        $result = $model->ServiceBanner($service_id);

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $result
        ]);
    }

    public function getDashboardBanner()
    {
        $user = authuser();

        $model = new MasterModel();
        $result = $model->getDashboardBanner($user->role_id);

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $result
        ]);
    }

    public function getOrderhistory()
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

        $model = new MasterModel();
        $user = authuser();

        $result = $model->Orderhistory($page, $user->id);

        $current_batch = count($model->Orderhistory($page, $user->id));

        $count = $model->getOrderCount($user->id);

        if (!is_null($result)) {
            return $this->respond([
                'status' => true,
                'message' => 'Success',
                'data' => [
                    'items' => $result,
                    'total_count' => $count->total_count,
                    'current_batch' => $current_batch
                ]
            ]);
        }
    }

    public function getBannerType()
    {
        $model = new MasterModel();
        $result = $model->getBannerType();

        if (!is_null($result)) {
            return $this->respond([
                'status' => true,
                'message' => 'Success',
                'data' => $result
            ]);
        }
    }

    public function getCareerGuidanceBanner()
    {
        $rules = [
            'type' => [
                'required',
                static function ($value, $data, &$error, $field) {

                    if ($value != '') {
                        $db = db_connect();

                        $result = $db->query("SELECT * FROM tbl_banner_types WHERE type = ?", [$value])->getRow();

                        if (is_null($result) || empty($result)) {
                            $error = $value . ' invalid type';
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

        $type = $this->request->getVar('type');
        $model = new MasterModel();
        $result = $model->getBanner($type);

        if (!is_null($result)) {
            return $this->respond([
                'status' => true,
                'message' => 'Success',
                'data' => $result
            ]);
        }
    }

    public function getApplyData()
    {
        $data = [
            [
                'title' => 'Free College admission form fill up tutorial',
                'video_url' => 'https://www.youtube.com/embed/OMBEtL6-mnU?rel=0'
            ],
            [
                'title' => 'Free College admission form fill up tutorial',
                'video_url' => 'https://www.youtube.com/embed/OMBEtL6-mnU?rel=0'
            ],
            [
                'title' => 'Free College admission form fill up tutorial',
                'video_url' => 'https://www.youtube.com/embed/OMBEtL6-mnU?rel=0'
            ],

        ];

        return $this->respond([
            'status' => true,
            'message' => 'Success',
            'data' => $data
        ]);
    }
}
