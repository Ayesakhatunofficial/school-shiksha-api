<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\Exceptions\DataException;
use DateTime;

class StudentModel extends Model
{
    protected $table = 'tbl_students';
    protected $primaryKey       = 'id';
    protected $allowedFields    = ['id_card_front', 'id_card_back', 'id_card_data'];

    /**
     * Get Agent by referral code
     * 
     * @param string $referral_code
     * @return object
     */
    public function getAgent($referral_code)
    {
        return $this->db->table('tbl_users')
            ->where('username', $referral_code)
            ->get()
            ->getRow();
    }

    /**
     * register student 
     *
     * @param array $post_data
     * @return bool
     */
    public function registerUser(array $post_data)
    {
        try {
            $role = $this->db->query("SELECT * FROM tbl_roles WHERE role_name = 'student'")->getRow();

            $userId = getNextStudentId(ROLE_STUDENT);

            $plan_id = $post_data['plan_id'];

            $plan_details = $this->db->table('tbl_plans')
                ->where('id', $plan_id)
                ->get()
                ->getRow();

            if ($plan_details->plan_amount == 0) {

                $this->db->transException(true)->transStart();

                $user_data = [
                    'username' => $userId,
                    'role_id' => $role->id,
                    'name' => $post_data['name'],
                    'email' => $post_data['email'],
                    'mobile' => $post_data['mobile'],
                    'password' => $post_data['password'],
                    'is_active' => 1,
                    'created_by' => (isset($post_data['affilate_agent_id']) && intval($post_data['affilate_agent_id']) > 0) ? $post_data['affilate_agent_id'] : NULL
                ];

                $this->db->table('tbl_users')->insert($user_data);
                $std_insert_id = $this->db->insertID();

                if ($std_insert_id) {

                    $student_data = [
                        'user_id' => $std_insert_id,
                        'affilate_agent_id' => isset($post_data['affilate_agent_id']) ? $post_data['affilate_agent_id'] : NULL,
                        'name' => $post_data['name'],
                        'email' => $post_data['email'],
                        'date_of_birth' => $post_data['date_of_birth'],
                        'father_name' => $post_data['father_name'],
                        'gender' => $post_data['gender'],
                        'pincode' => $post_data['pincode'],
                        'district_id' => $post_data['district_id'],
                        'police_station' => $post_data['police_station'],
                        // 'nationality' => $post_data['nationality'],
                        // 'religion' => $post_data['religion'],
                        'address' => $post_data['address'],
                        'whatsapp_number' => $post_data['whatsapp_number'],
                        'class_id' => $post_data['class_id']
                    ];

                    $this->db->table('tbl_students')->insert($student_data);
                    $student_id = $this->db->insertID();
                }

                $inv_id = getInvoiceId();

                $invoice_history = [
                    'user_id' => $std_insert_id,
                    'uniq_invoice_id' => $inv_id,
                    'invoice_date' => date('y-m-d'),
                    'due_date' => date('Y-m-d'),
                    'subtotal' => $plan_details->plan_amount,
                    'discount' => 0,
                    'total' => $plan_details->plan_amount,
                    'status' => 'paid',
                ];

                $this->db->table('tbl_invoices')->insert($invoice_history);
                $insert_id = $this->db->insertID();

                if ($insert_id) {
                    $inv_item_data = [
                        'invoice_id' => $insert_id,
                        'item_description' => $plan_details->plan_name,
                        'quantity' => 1,
                        'unit_amount' => $plan_details->plan_amount,
                        'amount' => 1 * $plan_details->plan_amount
                    ];

                    $this->db->table('tbl_invoice_items')->insert($inv_item_data);

                    $currentTime = new DateTime();

                    $start_time = $currentTime->format('Y-m-d H:i:s');

                    $currentTime->modify("$plan_details->plan_duration months");

                    $end_time = $currentTime->format('Y-m-d H:i:s');

                    $subscription_data = [
                        'invoice_id' => $insert_id,
                        'user_id' => $std_insert_id,
                        'plan_id' => $plan_id,
                        'plan_services' => $plan_details->plan_name,
                        'subscription_status' => 'active',
                        'plan_interval' => 'month',
                        'plan_interval_count' => $plan_details->plan_duration,
                        'plan_period_start' => $start_time,
                        'plan_period_end' => $end_time
                    ];

                    $this->db->table('tbl_user_subscriptions')->insert($subscription_data);

                    $id_data = [
                        'username' => $userId,
                        'name' => $post_data['name'],
                        'mobile' => $post_data['mobile'],
                        'address' => $post_data['address'],
                        'date_of_birth' => $post_data['date_of_birth'],
                        'plan_period_end' => $end_time,
                        'id' => $student_id,
                        'plan_name' => $plan_details->plan_name,
                        'plan_id' => $plan_id
                    ];

                    idCardGenerate($id_data);
                }

                if (isset($post_data['affilate_agent_id'])) {

                    $agent = getUserById($post_data['affilate_agent_id']);

                    if ($agent->role_name == ROLE_AFFILATE_AGENT) {
                        //check the agent status 
                        $role_id = $agent->role_id;

                        //add commission according to the plan 
                        $plan_commission = getPlanCommission($role_id, $plan_id);

                        if (!is_null($plan_commission) && $plan_commission->amount > 0) {

                            $commission = $plan_commission->amount;

                            if (!is_null($agent->current_plan) && $agent->current_plan->plan_type == 'free_plan') {
                                $agent_commission = $commission / 2;
                            } else {
                                $agent_commission = $commission;
                            }

                            $sql = "UPDATE tbl_users
                                    SET wallet = wallet + $agent_commission
                                    WHERE id = $agent->id";

                            if ($this->db->query($sql)) {
                                $n = 10;
                                $ref_no = generateNumeric($n);

                                $wallet_history = [
                                    'user_id' => $agent->id,
                                    'amount' => $agent_commission,
                                    'txn_type' => 'cr',
                                    'txn_comment' => 'commission for student plan',
                                    'ref_number' => $ref_no,
                                    'txn_date' => date('Y-m-d')
                                ];

                                $this->db->table('tbl_wallet_txn_history')->insert($wallet_history);
                            }

                            // find distibutor and check their plan status

                            $distributor = getUserById($agent->created_by);

                            if (!is_null($distributor)) {

                                // add commission according to the current plan

                                if (!is_null($distributor->current_plan) && $distributor->current_plan->plan_type == 'free_plan') {
                                    $commission_rate = 10;
                                } else {
                                    $commission_rate = 30;
                                }
                                $distributor_commission = $agent_commission * $commission_rate / 100;

                                // create wallet txn record
                                // update walllet amount

                                $sql = "UPDATE tbl_users
                                    SET wallet = wallet + $distributor_commission
                                    WHERE id = $distributor->id";

                                if ($this->db->query($sql)) {
                                    $n = 10;
                                    $ref_no = generateNumeric($n);

                                    $wallet_history = [
                                        'user_id' => $distributor->id,
                                        'amount' => $distributor_commission,
                                        'txn_type' => 'cr',
                                        'txn_comment' => 'commission for student plan',
                                        'ref_number' => $ref_no,
                                        'txn_date' => date('Y-m-d')
                                    ];

                                    $this->db->table('tbl_wallet_txn_history')->insert($wallet_history);
                                }

                                // find super distributor
                                $master_distributor = getUserById($distributor->created_by);

                                if (!is_null($master_distributor)) {
                                    // add commission according to the current plan

                                    if (!is_null($master_distributor->current_plan) && $master_distributor->current_plan->plan_type == 'free_plan') {
                                        $commission_rate = 10;
                                    } else {
                                        $commission_rate = 30;
                                    }

                                    $master_distributor_commission = $distributor_commission * $commission_rate / 100;

                                    $sql = "UPDATE tbl_users
                                    SET wallet = wallet + $master_distributor_commission
                                    WHERE id = $master_distributor->id";

                                    if ($this->db->query($sql)) {
                                        $n = 10;
                                        $ref_no = generateNumeric($n);

                                        $wallet_history = [
                                            'user_id' => $master_distributor->id,
                                            'amount' => $master_distributor_commission,
                                            'txn_type' => 'cr',
                                            'txn_comment' => 'commission for student plan',
                                            'ref_number' => $ref_no,
                                            'txn_date' => date('Y-m-d')
                                        ];

                                        $this->db->table('tbl_wallet_txn_history')->insert($wallet_history);
                                    }
                                }
                            }
                        }
                    }
                }

                $this->db->transComplete();
                return true;
            } else {
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

                $response = json_decode($response);

                if ($response) {
                    $order_data = [
                        'order_id' => $response->client_orderid,
                        'student_data' => json_encode($post_data)
                    ];

                    $this->db->table('tbl_orders')->insert($order_data);
                }

                return $response;
            }
        } catch (DatabaseException $e) {
            return false;
        }
    }

    /**
     * ADD student data after payment for plan 
     * 
     * @param string $order_id
     * @return bool
     */
    public function addStudentData($order_id)
    {
        $role = $this->db->query("SELECT * FROM tbl_roles WHERE role_name = 'student'")->getRow();

        $userId = getNextStudentId(ROLE_STUDENT);

        $student_data = $this->db->table('tbl_orders')
            ->where('order_id', $order_id)
            ->get()
            ->getRow();

        $post_data = json_decode($student_data->student_data);

        try {

            $plan_id = $post_data->plan_id;

            $plan_details = $this->db->table('tbl_plans')
                ->where('id', $plan_id)
                ->get()
                ->getRow();

            $this->db->transException(true)->transStart();

            $user_data = [
                'username' => $userId,
                'role_id' => $role->id,
                'name' => $post_data->name,
                'email' => $post_data->email,
                'mobile' => $post_data->mobile,
                'password' => $post_data->password,
                'is_active' => 1,
                'created_by' => isset($post_data->affilate_agent_id) ? $post_data->affilate_agent_id : ''
            ];

            $this->db->table('tbl_users')->insert($user_data);
            $std_insert_id = $this->db->insertID();

            $student_data = [
                'user_id' => $std_insert_id,
                'affilate_agent_id' => isset($post_data->affilate_agent_id) ? $post_data->affilate_agent_id : NULL,
                'name' => $post_data->name,
                'email' => $post_data->email,
                'date_of_birth' => $post_data->date_of_birth,
                'father_name' => $post_data->father_name,
                'gender' => $post_data->gender,
                'pincode' => $post_data->pincode,
                'district_id' => $post_data->district_id,
                'police_station' => $post_data->police_station,
                // 'nationality' => $post_data->nationality,
                'religion' => $post_data->religion,
                'address' => $post_data->address,
                'whatsapp_number' => $post_data->whatsapp_number,
                'class_id' => $post_data->class_id
            ];

            $this->db->table('tbl_students')->insert($student_data);
            $student_id = $this->db->insertID();

            $inv_id = getInvoiceId();

            $invoice_history = [
                'user_id' => $std_insert_id,
                'uniq_invoice_id' => $inv_id,
                'invoice_date' => date('y-m-d'),
                'due_date' => date('Y-m-d'),
                'subtotal' => $plan_details->plan_amount,
                'discount' => 0,
                'total' => $plan_details->plan_amount,
                'status' => 'paid',
            ];

            $this->db->table('tbl_invoices')->insert($invoice_history);
            $insert_id = $this->db->insertID();

            if ($insert_id) {
                $inv_item_data = [
                    'invoice_id' => $insert_id,
                    'item_description' => $plan_details->plan_name,
                    'quantity' => 1,
                    'unit_amount' => $plan_details->plan_amount,
                    'amount' => 1 * $plan_details->plan_amount
                ];

                $this->db->table('tbl_invoice_items')->insert($inv_item_data);

                $currentTime = new DateTime();

                $start_time = $currentTime->format('Y-m-d H:i:s');

                $currentTime->modify("$plan_details->plan_duration months");

                $end_time = $currentTime->format('Y-m-d H:i:s');

                $subscription_data = [
                    'invoice_id' => $insert_id,
                    'user_id' => $std_insert_id,
                    'plan_id' => $plan_id,
                    'plan_services' => $plan_details->plan_name,
                    'subscription_status' => 'active',
                    'plan_interval' => 'month',
                    'plan_interval_count' => $plan_details->plan_duration,
                    'plan_period_start' => $start_time,
                    'plan_period_end' => $end_time
                ];

                $this->db->table('tbl_user_subscriptions')->insert($subscription_data);

                $id_data = [
                    'username' => $userId,
                    'name' => $post_data->name,
                    'mobile' => $post_data->mobile,
                    'address' => $post_data->address,
                    'date_of_birth' => $post_data->date_of_birth,
                    'plan_period_end' => $end_time,
                    'id' => $student_id,
                    'plan_name' => $plan_details->plan_name,
                    'plan_id' => $plan_id
                ];

                idCardGenerate($id_data);
            }

            if (isset($post_data->affilate_agent_id)) {
                $agent = getUserById($post_data->affilate_agent_id);

                if ($agent->role_name == ROLE_AFFILATE_AGENT) {
                    //check the agent status 
                    $role_id = $agent->role_id;

                    //add commission according to the plan 
                    $plan_commission = getPlanCommission($role_id, $plan_id);

                    if (!is_null($plan_commission) && $plan_commission->amount > 0) {

                        $commission = $plan_commission->amount;

                        if (!is_null($agent->current_plan) && $agent->current_plan->plan_type == 'free_plan') {
                            $agent_commission = $commission / 2;
                        } else {
                            $agent_commission = $commission;
                        }

                        $sql = "UPDATE tbl_users
                                SET wallet = wallet + $agent_commission
                                WHERE id = $agent->id";

                        if ($this->db->query($sql)) {
                            $n = 10;
                            $ref_no = generateNumeric($n);

                            $wallet_history = [
                                'user_id' => $agent->id,
                                'commission_from' => $std_insert_id,
                                'amount' => $agent_commission,
                                'txn_type' => 'cr',
                                'txn_comment' => 'commission for student plan',
                                'ref_number' => $ref_no,
                                'txn_date' => date('Y-m-d')
                            ];

                            $this->db->table('tbl_wallet_txn_history')->insert($wallet_history);
                        }

                        // find distibutor and check their plan status

                        $distributor = getUserById($agent->created_by);

                        if (!is_null($distributor)) {

                            // add commission according to the current plan

                            if (!is_null($distributor->current_plan) && $distributor->current_plan->plan_type == 'free_plan') {
                                $commission_rate = 10;
                            } else {
                                $commission_rate = 30;
                            }
                            $distributor_commission = $agent_commission * $commission_rate / 100;

                            // create wallet txn record
                            // update walllet amount

                            $sql = "UPDATE tbl_users
                                SET wallet = wallet + $distributor_commission
                                WHERE id = $distributor->id";

                            if ($this->db->query($sql)) {
                                $n = 10;
                                $ref_no = generateNumeric($n);

                                $wallet_history = [
                                    'user_id' => $distributor->id,
                                    'commission_from' => $agent->id,
                                    'amount' => $distributor_commission,
                                    'txn_type' => 'cr',
                                    'txn_comment' => 'commission for student plan',
                                    'ref_number' => $ref_no,
                                    'txn_date' => date('Y-m-d')
                                ];

                                $this->db->table('tbl_wallet_txn_history')->insert($wallet_history);
                            }

                            // find super distributor
                            $master_distributor = getUserById($distributor->created_by);

                            if (!is_null($master_distributor)) {
                                // add commission according to the current plan

                                if (!is_null($master_distributor->current_plan) && $master_distributor->current_plan->plan_type == 'free_plan') {
                                    $commission_rate = 10;
                                } else {
                                    $commission_rate = 30;
                                }

                                $master_distributor_commission = $distributor_commission * $commission_rate / 100;

                                $sql = "UPDATE tbl_users
                                SET wallet = wallet + $master_distributor_commission
                                WHERE id = $master_distributor->id";

                                if ($this->db->query($sql)) {
                                    $n = 10;
                                    $ref_no = generateNumeric($n);

                                    $wallet_history = [
                                        'user_id' => $master_distributor->id,
                                        'commission_from' => $distributor->id,
                                        'amount' => $master_distributor_commission,
                                        'txn_type' => 'cr',
                                        'txn_comment' => 'commission for student plan',
                                        'ref_number' => $ref_no,
                                        'txn_date' => date('Y-m-d')
                                    ];

                                    $this->db->table('tbl_wallet_txn_history')->insert($wallet_history);
                                }
                            }
                        }
                    }
                }
            }

            $this->db->transComplete();

            return true;
        } catch (DatabaseException $e) {
            return false;
        }
    }

    /**
     * get user by username 
     * 
     * @param string|int
     * @return object
     */
    public function getUser($username)
    {
        return $this->db->query("SELECT * FROM tbl_users WHERE mobile = ? OR username = ?", [
            $username, $username
        ])->getRow();
    }

    /**
     * get user by id 
     * 
     * @param int $user_id
     * @return object
     */
    public function findUserByUserId($user_id)
    {
        return $this->db->query("SELECT * FROM tbl_users WHERE id = ?", [
            $user_id
        ])->getRow();
    }

    /**
     * get student by email
     * 
     * @param string $email
     * @return object
     */
    public function getStudent($email)
    {
        return $this->db->table('tbl_users')
            ->where('email', $email)
            ->get()
            ->getRow();
    }

    /**
     * update data
     * 
     * @param array $data
     * @param string $email
     * @return bool
     */
    public function updateData($data, $email)
    {
        return $this->db->table('tbl_users')
            ->where('email', $email)
            ->update($data);
    }

    /**
     * update password with mobile 
     * 
     * @param int $mobile
     * @param string $password
     * @return bool
     */
    public function updatePassword($mobile, $password)
    {
        $user = authuser();

        $data = [
            'password' => md5($password),
            'updated_by' => $user->id
        ];

        return $this->db->table('tbl_users')
            ->where('mobile', $mobile)
            ->update($data);
    }

    /**
     * get profile data by id
     * 
     * @param int $id
     * @return object
     */
    public function getProfileData($id)
    {
        $sql = "SELECT
                    u.mobile as mobile,
                    d.id as district_id,
                    d.name as district_name,
                    s.id as state_id,
                    s.name as state_name,
                    c.id as class_id,
                    c.name as class_name,
                    st.*
                FROM 
                    tbl_students st  
                JOIN tbl_users u ON u.id = st.user_id 
                JOIN tbl_districts d ON d.id = st.district_id
                JOIN tbl_states s ON s.id = d.state_id
                JOIN tbl_classes c ON c.id = st.class_id
                WHERE st.user_id = $id ";

        $user =  $this->db->query($sql)->getRow();

        if (!is_null($user)) {
            $sql = "SELECT 
                        p.plan_name,
                        p.plan_amount,
                        p.plan_duration,
                        p.plan_description,
                        s.* 
                    FROM 
                        tbl_user_subscriptions s
                    JOIN tbl_plans p ON p.id = s.plan_id
                    WHERE s.user_id = $id AND s.subscription_status = 'active' ";

            $user->subscription = $this->db->query($sql)->getRow();

            $plan_id = $user->subscription->plan_id;

            $sql = "SELECT 
                       ps.service_id
                    FROM 
                        tbl_plan_services ps
                    WHERE ps.plan_id = $plan_id";

            $plan_services = $this->db->query($sql)->getResult();

            $serviceIds = array_column($plan_services, 'service_id');

            $user->subscription->plan_services = $serviceIds;
        }

        return $user;
    }

    /**
     * update profile 
     * 
     * @param array $data
     * @return bool
     */
    public function updateProfile($data)
    {
        try {
            $user = authuser();
            $update_data = [
                'name' => $data['name'],
                'email' => $data['email'],
                'father_name' => $data['father_name'],
                'whatsapp_number' => $data['whatsapp_number'],
                'class_id' => $data['class_id'],
                'date_of_birth' => $data['date_of_birth'],
                'gender' => $data['gender'],
                // 'nationality' => $data['nationality'],
                // 'religion' => $data['religion'],
                'district_id' => $data['district_id'],
                'police_station' => $data['police_station'],
                'pincode' => $data['pincode'],
                'address' => $data['address'],
            ];

            $this->db->table('tbl_students')
                ->where('user_id', $data['id'])
                ->update($update_data);

            $user_data = [
                'email' => $data['email'],
                'updated_by' => $user->id
            ];

            $this->db->table('tbl_users')
                ->where('id', $data['id'])
                ->update($user_data);

            $student = $this->db->table('tbl_students')
                ->join('tbl_users', 'tbl_users.id = tbl_students.user_id', 'inner')
                ->join('tbl_user_subscriptions', 'tbl_user_subscriptions.user_id = tbl_students.user_id', 'inner')
                ->join('tbl_plans', 'tbl_plans.id = tbl_user_subscriptions.plan_id', 'inner')
                ->select('tbl_students.id,
                tbl_students.name,
                tbl_students.address,
                tbl_students.date_of_birth,
                tbl_students.id_card_front,
                tbl_students.id_card_back,
                tbl_students.id_card_data,
                tbl_users.username,
                tbl_users.mobile,
                tbl_user_subscriptions.plan_period_end,
                tbl_plans.plan_name,
                tbl_plans.id as plan_id')
                ->where('tbl_students.user_id', $data['id'])
                ->where('tbl_user_subscriptions.subscription_status', 'active')
                ->get()
                ->getRowArray();

            idCardGenerate($student);

            return true;
        } catch (DataException $e) {
            return false;
        }
    }

    /**
     * Get order details by id 
     * 
     * @param int $user_id
     * @param int $page
     * @return array[object]
     */
    public function getOrderDetails($user_id, $page)
    {
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT 
                    it.item_description,
                    i.*
                FROM 
                    tbl_invoices i
                JOIN tbl_invoice_items it ON it.invoice_id = i.id
                WHERE i.user_id = ? AND total > 0
                ORDER BY i.id DESC
                LIMIT ? OFFSET ? ";

        return $this->db->query($sql, [$user_id, $limit, $offset])->getResult();
    }

    /**
     * Get payment order details count 
     * 
     * @param int $user_id
     * @return object
     */
    public function getOrderDetailsCount($user_id)
    {
        $sql = "SELECT
                    COUNT(id) as total_count
                FROM 
                    tbl_invoices
                WHERE user_id = ? AND total > 0";
        return $this->db->query($sql, [$user_id])->getRow();
    }

    /**
     * Get order details by id 
     * 
     * @param int $user_id
     * @param int $page
     * @return array[object]
     */
    public function getNotifications($user_id, $page)
    {
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT 
                    *
                FROM 
                    tbl_notifications
                WHERE user_id = ? 
                ORDER BY id DESC
                LIMIT ? OFFSET ? ";

        return $this->db->query($sql, [$user_id, $limit, $offset])->getResult();
    }

    /**
     * Get notification count
     * 
     * @param int $user_id
     * @return object
     */
    public function getNotificationCount($user_id)
    {
        $sql = "SELECT 
                    COUNT(id) as total_count
                FROM 
                    tbl_notifications
                WHERE user_id = ? ";

        return $this->db->query($sql, [$user_id])->getRow();
    }

    /**
     * Get notification by id and update notification to read
     * 
     * @param int $id
     * @return object
     */
    public function getNotificationById($id)
    {
        $update = [
            'is_read' => 1
        ];
        $this->db->table('tbl_notifications')
            ->where('id', $id)
            ->update($update);

        return $this->db->table('tbl_notifications')
            ->where('id', $id)
            ->get()
            ->getRow();
    }

    /**
     * Get unread notifications count by user id 
     * 
     * @param int $user_id
     * @return object
     */
    public function getNotificationCountById($user_id)
    {
        $sql = "SELECT
                    COUNT(id) as unread_count
                FROM 
                    tbl_notifications
                WHERE user_id = ? AND is_read = 0 ";
        return $this->db->query($sql, [$user_id])->getRow();
    }

    /**
     * Add query
     * 
     * @param array $post_data
     * @param int $user_id
     * @return bool
     */
    public function addQuery($post_data, $user_id)
    {
        $data = [
            'user_id' => $user_id,
            'name' => $post_data['name'],
            'email' => $post_data['email'],
            'mobile' => $post_data['mobile'],
            'message' => $post_data['message']
        ];

        return $this->db->table('tbl_queries')->insert($data);
    }

    /**
     * Get plan details
     * 
     * @param int $plan_id
     * @return object
     */
    public function getPlanDetails($plan_id)
    {
        return $this->db->table('tbl_plans')
            ->where('id', $plan_id)
            ->get()
            ->getRow();
    }


    /**
     * Add plan for student or renew plan
     * 
     * @param int $plan_id
     * @param int $stud_id
     * @param string $payment_txn_id
     * 
     * @return bool
     */
    public function addSubscription($plan_id, $stud_id, $payment_txn_id)
    {
        try {
            $this->db->transException(true)->transStart();

            $plan_details = $this->db->table('tbl_plans')
                ->where('id', $plan_id)
                ->get()
                ->getRow();

            $exist_sub = $this->db->table('tbl_user_subscriptions')
                ->where('user_id', $stud_id)
                ->where('subscription_status', 'active')
                ->get()
                ->getRow();

            if (!empty($exist_sub)) {
                $sub_data = [
                    'subscription_status' => 'cancelled'
                ];


                $this->db->table('tbl_user_subscriptions')
                    ->where('id', $exist_sub->id)
                    ->update($sub_data);
            }

            $inv_id = getInvoiceId();

            $invoice_history = [
                'user_id' => $stud_id,
                'uniq_invoice_id' => $inv_id,
                'invoice_date' => date('y-m-d'),
                'due_date' => date('Y-m-d'),
                'subtotal' => $plan_details->plan_amount,
                'discount' => 0,
                'total' => $plan_details->plan_amount,
                'status' => 'paid',
                'payment_txn_id' => $payment_txn_id
            ];

            $this->db->table('tbl_invoices')->insert($invoice_history);
            $insert_id = $this->db->insertID();

            if ($insert_id) {
                $inv_item_data = [
                    'invoice_id' => $insert_id,
                    'item_description' => $plan_details->plan_name,
                    'quantity' => 1,
                    'unit_amount' => $plan_details->plan_amount,
                    'amount' => 1 * $plan_details->plan_amount
                ];

                $this->db->table('tbl_invoice_items')->insert($inv_item_data);

                $currentTime = new DateTime();

                $start_time = $currentTime->format('Y-m-d H:i:s');

                $currentTime->modify("$plan_details->plan_duration months");

                $end_time = $currentTime->format('Y-m-d H:i:s');

                $subscription_data = [
                    'invoice_id' => $insert_id,
                    'user_id' => $stud_id,
                    'plan_id' => $plan_id,
                    'plan_services' => $plan_details->plan_name,
                    'subscription_status' => 'active',
                    'plan_interval' => 'month',
                    'plan_interval_count' => $plan_details->plan_duration,
                    'plan_period_start' => $start_time,
                    'plan_period_end' => $end_time
                ];

                $this->db->table('tbl_user_subscriptions')->insert($subscription_data);

                $student = $this->db->table('tbl_students')
                    ->join('tbl_users', 'tbl_users.id = tbl_students.user_id', 'inner')
                    ->join('tbl_user_subscriptions', 'tbl_user_subscriptions.user_id = tbl_students.user_id', 'inner')
                    ->join('tbl_plans', 'tbl_plans.id = tbl_user_subscriptions.plan_id', 'inner')
                    ->select('tbl_students.id,
                    tbl_students.name,
                    tbl_students.address,
                    tbl_students.date_of_birth,
                    tbl_students.id_card_front,
                    tbl_students.id_card_back,
                    tbl_students.id_card_data,
                    tbl_users.username,
                    tbl_users.mobile,
                    tbl_user_subscriptions.plan_period_end,
                    tbl_plans.plan_name,
                    tbl_plans.id as plan_id')
                    ->where('tbl_students.user_id', $stud_id)
                    ->where('tbl_user_subscriptions.subscription_status', 'active')
                    ->get()
                    ->getRowArray();

                idCardGenerate($student);
            }

            $user = authuser();

            if (isset($user->created_by)) {

                $agent = getUserById($user->id);

                if ($agent->role_name == ROLE_AFFILATE_AGENT) {
                    //check the agent status 
                    $role_id = $agent->role_id;

                    //add commission according to the plan 
                    $plan_commission = getPlanCommission($role_id, $plan_id);

                    if (!is_null($plan_commission) && $plan_commission->amount > 0) {

                        $commission = $plan_commission->amount;

                        if (!is_null($agent->current_plan) && $agent->current_plan->plan_type == 'free_plan') {
                            $agent_commission = $commission / 2;
                        } else {
                            $agent_commission = $commission;
                        }

                        $sql = "UPDATE tbl_users
                                SET wallet = wallet + $agent_commission
                                WHERE id = $agent->id";

                        if ($this->db->query($sql)) {
                            $n = 10;
                            $ref_no = generateNumeric($n);

                            $wallet_history = [
                                'user_id' => $agent->id,
                                'commission_from' => $stud_id,
                                'amount' => $agent_commission,
                                'txn_type' => 'cr',
                                'txn_comment' => 'commission for student plan',
                                'ref_number' => $ref_no,
                                'txn_date' => date('Y-m-d')
                            ];

                            $this->db->table('tbl_wallet_txn_history')->insert($wallet_history);
                        }

                        // find distibutor and check their plan status

                        $distributor = getUserById($agent->created_by);

                        if (!is_null($distributor)) {

                            // add commission according to the current plan

                            if (!is_null($distributor->current_plan) && $distributor->current_plan->plan_type == 'free_plan') {
                                $commission_rate = 10;
                            } else {
                                $commission_rate = 30;
                            }
                            $distributor_commission = $agent_commission * $commission_rate / 100;

                            // create wallet txn record
                            // update walllet amount

                            $sql = "UPDATE tbl_users
                                SET wallet = wallet + $distributor_commission
                                WHERE id = $distributor->id";

                            if ($this->db->query($sql)) {
                                $n = 10;
                                $ref_no = generateNumeric($n);

                                $wallet_history = [
                                    'user_id' => $distributor->id,
                                    'commission_from' => $agent->id,
                                    'amount' => $distributor_commission,
                                    'txn_type' => 'cr',
                                    'txn_comment' => 'commission for student plan',
                                    'ref_number' => $ref_no,
                                    'txn_date' => date('Y-m-d')
                                ];

                                $this->db->table('tbl_wallet_txn_history')->insert($wallet_history);
                            }

                            // find super distributor
                            $master_distributor = getUserById($distributor->created_by);

                            if (!is_null($master_distributor)) {
                                // add commission according to the current plan

                                if (!is_null($master_distributor->current_plan) && $master_distributor->current_plan->plan_type == 'free_plan') {
                                    $commission_rate = 10;
                                } else {
                                    $commission_rate = 30;
                                }

                                $master_distributor_commission = $distributor_commission * $commission_rate / 100;

                                $sql = "UPDATE tbl_users
                                SET wallet = wallet + $master_distributor_commission
                                WHERE id = $master_distributor->id";

                                if ($this->db->query($sql)) {
                                    $n = 10;
                                    $ref_no = generateNumeric($n);

                                    $wallet_history = [
                                        'user_id' => $master_distributor->id,
                                        'commission_from' => $distributor->id,
                                        'amount' => $master_distributor_commission,
                                        'txn_type' => 'cr',
                                        'txn_comment' => 'commission for student plan',
                                        'ref_number' => $ref_no,
                                        'txn_date' => date('Y-m-d')
                                    ];

                                    $this->db->table('tbl_wallet_txn_history')->insert($wallet_history);
                                }
                            }
                        }
                    }
                }
            }

            $this->db->transComplete();
            return true;
        } catch (DatabaseException $e) {
            return false;
        }
    }

    /**
     * Submit form data for college or course registration
     * 
     * @param array $post_data
     * @return bool 
     */
    public function addFormData($post_data)
    {
        $service_id = $post_data['service_id'];
        $user = authuser();
        if (isset($user->created_by) && $user->created_by != NULL && $user->created_by != '') {
            $agent = getUserById($user->created_by);
            $service_commission = $this->db->table('tbl_service_comissions')
                ->where('service_id', $service_id)
                ->where('role_id', $agent->role_id)
                ->get()
                ->getRow();
        }

        $enquery_number = getEnquiryNumber();

        $data = [
            'enquiry_number' => $enquery_number,
            'service_id' => $service_id,
            'organization_course_id' => $post_data['organization_course_id'],
            'enquiry_details' => $post_data['enquiry_details'],
            'service_commission_amount' => isset($service_commission->amount) ? $service_commission->amount : 0,
            'created_by' => $user->id
        ];

        if (isset($post_data['documents'])) {
            $data['documents'] = $post_data['documents'];
        }

        return $this->db->table('tbl_enquires')->insert($data);
    }

    /**
     * Add carrier guidance
     * 
     * @param array $post_data
     * @return bool
     */
    public function addCarrierGuide($post_data)
    {
        $user = authuser();

        $data = [
            'name' => $post_data['name'],
            'email' => $post_data['email'],
            'mobile' => $post_data['mobile'],
            'guardian_name' => $post_data['guardian_name'],
            'whatsapp_number' => $post_data['whatsapp_number'],
            'mp_percentage' => $post_data['mp_percentage'],
            'hs_percentage' => $post_data['hs_percentage'],
            'stream' => $post_data['stream'],
            'interest_course_name' => $post_data['interest_course_name'],
            'created_by' => $user->id
        ];

        return $this->db->table('tbl_carrier_guidlines')->insert($data);
    }

    /**
     * Add exnternal link apply record
     * 
     * @param int $user_id
     * @param int $org_course_id
     * @return bool
     */
    public function addExternalRecord($user_id, $org_course_id)
    {
        $data = [
            'user_id' => $user_id,
            'organization_course_id' => $org_course_id
        ];

        return $this->db->table('tbl_external_records')->insert($data);
    }
}
