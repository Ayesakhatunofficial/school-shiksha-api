<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

use App\Models\StudentModel;
use \CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Extract auth token from request header
 * 
 * @param string $authHeader
 * @return string
 */
function getBearerToken($authHeader)
{
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return $matches[1];
    }
}


/**
 * @var object $userData
 */
$userData = new stdClass();

/**
 * Set auth user
 * 
 * @return object|null
 */
function authuser()
{
    global $userData;

    return $userData;
}

/**
 * Set auth user
 * 
 * @param object $user
 */
function setAuthUser($user)
{
    global $userData;
    $userData = $user;
}

/**
 * Generate auth tokens
 * 
 * @param mixed $user
 * @return array
 */
function generateAuthToken($user): array
{
    $key = getenv("JWT_SECRET");

    $issuedAt   = new \DateTimeImmutable();
    $expire     = $issuedAt->modify('+60 day')->getTimestamp();  // Add 60 miniutes to expire on production
    $serverName = getenv('app.baseURL');

    $user_id = is_object($user) ? $user->id : $user['id'];

    $payload = [
        'iat'  => $issuedAt->getTimestamp(),         // Issued at: time when the token was generated
        'iss'  => $serverName,                       // Issuer
        'nbf'  => $issuedAt->getTimestamp(),         // Not before
        'exp'  => $expire,                           // Expire
        'user_id' =>  $user_id
    ];

    $jwt = \Firebase\JWT\JWT::encode($payload, $key, 'HS256');

    $refresh_token = hash('sha1', uniqid(md5($key .  $user_id)));

    // save fresh token behalf of user
    // $db = db_connect();

    // $db->table('tbl_users')->update([
    //     'refresh_token' => $refresh_token
    // ], ['id' =>  $user_id]);

    return [
        'access_token' => $jwt,
        'refresh_token' => $refresh_token,
        'expire_at' => $expire,
        'expire_at_string' => (new \DateTime())->setTimestamp($expire)->format('Y-m-d H:i:s')
    ];
}

/**
 * get settings data with key
 * 
 * @param string $key
 * @return string|null
 */
function getsetting($key)
{
    $db = db_connect();
    $sql = "SELECT setting_value FROM tbl_settings WHERE setting_name = '$key'";
    $result = $db->query($sql)->getRow();

    if (is_null($result)) {
        return null;
    }

    return $result->setting_value;
}

function generateNumericOTP($n)
{
    $generator = "1357902468";
    $result = "";

    for ($i = 1; $i <= $n; $i++) {
        $result .= substr($generator, (rand() % (strlen($generator))), 1);
    }

    return $result;
}



/**
 * Get next student id
 * 
 * @return string
 */
function getNextStudentId($role_name)
{
    $db = db_connect();

    $sql = "SELECT
            CASE
                WHEN r.role_name = 'super_admin' THEN CONCAT('ADM-', LPAD(IFNULL(COUNT(u.id), 0) + 1, 3, '0') ) 
                WHEN r.role_name = 'student' THEN CONCAT('STD-', LPAD(IFNULL(COUNT(u.id), 0) + 1, 3, '0') ) 
                WHEN r.role_name = 'master_distributor' THEN CONCAT('MDB-', LPAD(IFNULL(COUNT(u.id), 0) + 1, 3, '0') ) 
                WHEN r.role_name = 'distributor' THEN CONCAT('DB-', LPAD(IFNULL(COUNT(u.id), 0) + 1, 3, '0') ) 
                WHEN r.role_name = 'affiliate_agent' THEN CONCAT('AGT-', LPAD(IFNULL(COUNT(u.id), 0) + 1, 3, '0') ) 
            END AS next_student_id
        FROM tbl_users u 
        INNER JOIN tbl_roles r  ON r.id = u.role_id
        WHERE r.role_name = ?";

    $result = $db->query($sql, [$role_name])->getRow();

    return $result->next_student_id;
}


/**
 * Get roles 
 * 
 * @param string $role_name
 * @return int
 */
function getRoleId($role_name)
{
    $db = db_connect();

    $role = $db->table('tbl_roles')
        ->where('role_name', $role_name)
        ->get()
        ->getRow();

    return $role->id;
}


/**
 * get role name bu role id 
 * 
 * @param string|null $role_id
 * @return string
 */
function getRole($role_id = NULL)
{
    $db = db_connect();

    if ($role_id == NULL) {
        $user = authuser();

        $role_id = $user->role_id;
    }

    $role = $db->table('tbl_roles')->where('id', $role_id)->get()->getRow();

    return $role->role_name;
}

/**
 * Get invoice id
 * 
 * @return string
 */
function getInvoiceId()
{
    $db = db_connect();

    // $sql = "SELECT 
    //         CONCAT('INV-', LPAD(IFNULL(COUNT(tbl_invoices.id), 0) + 1, 4, '0') ) AS next_inv_id
    //       FROM  
    //         tbl_invoices  
    //       ";

    $sql = "SELECT 
                CONCAT('INV-', LPAD(IFNULL(MAX(CAST(SUBSTRING(uniq_invoice_id, 5, 4) AS UNSIGNED)), 0) + 1, 4, '0')) AS next_inv_id
            FROM 
                tbl_invoices";

    $result = $db->query($sql)->getRow();

    return $result->next_inv_id;
}


/**
 * Get user by id
 * 
 * @param int $user_id
 * @return object|null
 */
function getUserById($user_id)
{
    $db = db_connect();

    $sql = "SELECT 
                tbl_roles.id as role_id,
                tbl_roles.role_name as role_name,
                tbl_users.* 
            FROM 
                tbl_users
            JOIN tbl_roles ON tbl_users.role_id = tbl_roles.id
            WHERE tbl_users.id = $user_id";

    $user = $db->query($sql)->getRow();

    if (!is_null($user)) {
        // find user current active plan
        $sql = "SELECT 
                    s.*,
                CASE
                WHEN i.total = 0 THEN 'free_plan'
                ELSE 'paid_plan'
                END AS plan_type
                FROM tbl_user_subscriptions s
                INNER JOIN tbl_invoices i ON i.id = s.invoice_id
                WHERE s.user_id = ? AND s.subscription_status = ?";

        $user->current_plan = $db->query($sql, [$user->id, 'active'])->getRow();
    }

    return $user;
}

function generateNumeric($n)
{
    $generator = "1357902468";
    $result = "";

    for ($i = 1; $i <= $n; $i++) {
        $result .= substr($generator, (rand() % (strlen($generator))), 1);
    }

    return $result;
}

/**
 * Get plan commission 
 * 
 * @param int $role_id
 * @param int $plan_id
 * @return object|null
 */
function getPlanCommission($role_id, $plan_id)
{
    $db = db_connect();

    $sql = "SELECT 
              *
          FROM 
            tbl_plan_commission 
          WHERE plan_id = ? AND role_id = ? ";
    return $db->query($sql, [$plan_id, $role_id])->getRow();
}

/**
 * Get enquiry number 
 * 
 * @return string
 */
function getEnquiryNumber()
{
    $db = db_connect();

    // $sql = "SELECT 
    //         CONCAT('ENQ-', LPAD(IFNULL(COUNT(tbl_enquires.id), 0) + 1, 4, '0') ) AS next_enq_id
    //       FROM  
    //         tbl_enquires 
    //       ";
    $sql = "SELECT 
                CONCAT('ENQ-', LPAD(IFNULL(MAX(CAST(SUBSTRING(enquiry_number, 5, 4) AS UNSIGNED)), 0) + 1, 4, '0')) AS next_enq_id
            FROM 
                tbl_enquires";

    $result = $db->query($sql)->getRow();

    return $result->next_enq_id;
}

/**
 * Upload a file
 * 
 * @param \CodeIgniter\HTTP\Files\UploadedFile|string $file
 * @return null|array
 */
function uploadFile(UploadedFile|string $file)
{
    if ($file instanceof UploadedFile) {
        if (!$file->isValid()) {
            return "Not a validate file";
        }

        if ($file->hasMoved()) {
            return;
        }

        $newFileName = $file->getRandomName();
        $status = $file->move(UPLOAD_PATH, $newFileName);

        if ($status == false) {
            return;
        }

        $file_path = UPLOAD_PATH . $newFileName;
    } else {
        $file_path = $file;
    }

    if (!file_exists($file_path)) {
        return;
    }

    $curl = curl_init();
    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => UPLOAD_SERVER_BASE_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array('image' => new CURLFILE($file_path)),
        )
    );

    $response = curl_exec($curl);

    curl_close($curl);

    $data = json_decode($response, true);

    // unlink current file
    \unlink($file_path);

    return $data['data'];
}

/**
 * Add text to image 
 *  
 * @param int $fontSize
 * @param string $imagePath
 * @param array $textArray
 * 
 * @return string|null
 */
function addTextToImage($imagePath, $textArray, $fontSize)
{
    $image = imagecreatefrompng($imagePath);
    $textColor = imagecolorallocate($image, 0, 0, 0);
    $fontPath = FCPATH . '/assets/fonts/Ubuntu/Ubuntu-Regular.ttf';

    if (!file_exists($fontPath)) {
        throw new Exception("Font file not found: $fontPath");
    }

    foreach ($textArray as $text) {
        imagettftext($image, $fontSize, 0, $text['x_cord'], $text['y_cord'], $textColor, $fontPath, $text['text']);
    }

    $outputPath = FCPATH . 'assets/images/output.png';
    imagepng($image, $outputPath);

    imagedestroy($image);

    return $outputPath;
}

/**
 * Generate student id card
 * 
 * @param array $student
 * @return bool
 */
function idCardGenerate(array $student)
{
    $id = $student['id'];
    $date_of_birth =  $student['date_of_birth'] ? date("d-m-Y", strtotime($student['date_of_birth'])) : "";
    $newDate = $student['plan_period_end'] ? date("d-m-Y", strtotime($student['plan_period_end'])) : "";

    $textArray = [
        [
            'text' => $student['name'],
            'x_cord' => 400,
            'y_cord' => 260
        ],
        [
            'text' =>   $student['username'],
            'x_cord' => 765,
            'y_cord' => 260
        ],
        [
            'text' =>  $student['plan_name'],
            'x_cord' => 400,
            'y_cord' => 345
        ],
        [
            'text' => $student['mobile'],
            'x_cord' => 765,
            'y_cord' => 345
        ],
        [
            'text' =>  $student['address'],
            'x_cord' => 400,
            'y_cord' => 430
        ],
        [
            'text' => $date_of_birth,
            'x_cord' => 765,
            'y_cord' => 430
        ],
        [
            'text' => $newDate,
            'x_cord' => 400,
            'y_cord' => 530
        ],
    ];

    $frontImagePath = FCPATH . '/assets/images/1.png';
    $outputPath = addTextToImage($frontImagePath, $textArray, 18);
    $imageData = uploadFile($outputPath);

    $id_card_data = [
        'id_card_front' => $imageData['file_name'],
        'id_card_back' => base_url('public/assets/images/5.png'),
        'id_card_data' => json_encode([
            'plan_id' => $student['plan_id'],
            'plan_name' => $student['plan_name']
        ])
    ];

    $model = new StudentModel();
    $result = $model->update($id, $id_card_data);

    if ($result) {
        return true;
    } else {
        return false;
    }
}
