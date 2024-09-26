<?php

namespace App\Models;

use CodeIgniter\Model;

class MasterModel extends Model
{
    /**
     * Get states
     * 
     * @return array[object]
     */
    public function getState()
    {
        return $this->db->table('tbl_states')
            ->get()
            ->getResult();
    }

    /**
     * Get district by state id
     * 
     * @param int $state_id
     * @return array[object]
     */
    public function getDistrict($state_id)
    {
        return $this->db->table('tbl_districts')
            ->where('state_id', $state_id)
            ->where('is_active', 1)
            ->get()
            ->getResult();
    }

    /**
     * Get block by district id
     * 
     * @param int $district_id
     * @return array[object]
     */
    public function getBlock($district_id)
    {
        return $this->db->table('tbl_blocks')
            ->where('district_id', $district_id)
            ->where('is_active', 1)
            ->get()
            ->getResult();
    }

    /**
     * Get plans
     * 
     * @return array[object]
     */
    public function getPlan()
    {
        $role_id = getRoleId(ROLE_STUDENT);

        return $this->db->table('tbl_plans')
            ->where('role_id', $role_id)
            ->where('is_active', 1)
            ->get()
            ->getResult();
    }

    /**
     * Get services
     * 
     * @param string $service_type
     * @param int $limit
     * @return  array[object]
     */
    public function getServices($service_type, $limit = NULL)
    {
        if ($limit != '' || $limit != NULL) {

            if ($limit > 20) {
                $limit = 20;
            }

            return $this->db->table('tbl_services')
                ->where('intended_for', $service_type)
                ->where('is_active', 1)
                ->limit($limit)
                ->get()
                ->getResult();
        } else {
            return $this->db->table('tbl_services')
                ->select('*')
                ->where('intended_for', $service_type)
                ->where('is_active', 1)
                ->get()
                ->getResult();
        }
    }

    /**
     * Get service count
     * 
     * @param string $service_type
     * @return object
     */
    public function getServiceCount($service_type)
    {
        $sql = "SELECT 
                    COUNT(id) as total_count
                FROM 
                    tbl_services
                WHERE intended_for  = ? AND is_active = 1 ";

        return $this->db->query($sql, [$service_type])->getRow();
    }

    /**
     * get class
     * 
     * @return array[object]
     */
    public function getClass()
    {
        return $this->db->table('tbl_classes')
            ->get()
            ->getResult();
    }

    /**
     * get courses
     * 
     * @param int $service_id
     * @return array[object]
     */
    public function getCourse($service_id)
    {
        $sql = "SELECT
                    c.id as course_id,
                    c.name as course_name,
                    c.course_type,
                    cs.*
                FROM 
                    tbl_course_services cs
                JOIN tbl_organizations_course oc ON oc.id = cs.org_course_id
                JOIN tbl_courses c ON c.id = oc.course_id
                WHERE cs.service_id = ? 
                GROUP BY oc.course_id 
                ORDER BY course_name";

        return $this->db->query($sql, [$service_id])->getResult();
    }


    /**
     * get organization course
     * 
     * @param array $data
     * @return array[object]|array[array]
     */
    public function getOrganizationCourse($data)
    {
        $limit = 10;
        $offset = ($data['page'] - 1) * $limit;

        $service_id = $data['service_id'];

        $sql = "SELECT
                    o.name as organization_name,
                    o.logo,
                    o.type,
                    o.mobile,
                    o.whatsapp_number,
                    CASE 
                        WHEN sv.service_type = 'course' THEN 'Course Name'
                        WHEN sv.service_type = 'job' THEN 'Job Name'
                        WHEN sv.service_type = 'exam' THEN 'Exam Name'
                         WHEN sv.service_type = 'scholarship' THEN 'Scholarship Name'
                        WHEN sv.service_type = 'training' THEN 'Training Name'
                    END as service_type,
                    c.name as course_name,
                    c.course_details,
                    c.course_type,
                    s.id as state_id,
                    s.name as state_name,
                    sv.required_field,
                    sv.terms_and_conditions,
                    d.id as district_id,
                    d.name as district_name,
                    b.id as block_id,
                    b.name as block_name,
                    oc.id as organization_course_id,
                    oc.organization_id,
                    oc.course_id,
                    CASE 
                        WHEN sv.service_type = 'course' THEN 'Course Fees'
                        WHEN sv.service_type = 'job' THEN 'Salary'
                        WHEN sv.service_type = 'exam' THEN 'Exam Fees'
                        WHEN sv.service_type = 'scholarship' THEN 'Scholarship Amount'
                        WHEN sv.service_type = 'training' THEN 'Training Fees'
                    END as fees_type,
                    oc.course_fees,
                    oc.course_duration,
                    oc.register_through,
                    oc.last_submission_date,
                    oc.url,
                    oc.extra_data,
                    oc.eligibility,
                    sc.*
                FROM 
                    tbl_course_services sc
                JOIN tbl_organizations_course oc ON oc.id = sc.org_course_id
                JOIN tbl_organizations o ON o.id = oc.organization_id
                JOIN tbl_courses c ON c.id = oc. course_id
                JOIN tbl_services sv ON sv.id = sc.service_id
                JOIN tbl_states s ON s.id = o.state_id
                JOIN tbl_districts d ON d.id = o.district_id
                JOIN tbl_blocks b ON b.id = o.block_id
                WHERE sc.service_id = $service_id";

        if ($data['search_value'] != '') {

            $like = " LIKE '%" . htmlspecialchars(trim($data['search_value'])) . "%'";

            $sql .= " AND ( 
                c.name $like
                OR o.name $like
                OR s.name $like
                OR d.name $like
                OR b.name $like 
            )";
        }

        if ($data['state_id'] != '') {
            $state_id = $data['state_id'];
            $sql .= "  AND o.state_id = $state_id";
        }

        if ($data['district_id'] != '') {
            $district_id = $data['district_id'];
            $sql .= "  AND o.district_id = $district_id";
        }

        if ($data['block_id'] != '') {
            $block_id = $data['block_id'];
            $sql .= "  AND o.block_id = $block_id";
        }

        if ($data['course_id'] != '') {
            $course_id = $data['course_id'];
            $sql .= " AND c.id = $course_id";
        }

        $total = $this->db->query($sql)->getResult();

        $sql .= " ORDER BY o.name ASC LIMIT $limit OFFSET $offset ";

        $result = $this->db->query($sql)->getResult();

        return [
            'data' => $result,
            'total_count' => count($total)
        ];
    }

    /**
     * Get course details 
     * 
     * @param int $id
     * @return object
     */
    public function getCourseDetailsById($id)
    {
        $sql = "SELECT 
                    o.name as organization_name,
                    o.logo,
                    o.type,
                    o.mobile,
                    o.whatsapp_number,
                    c.name as course_name,
                    c.course_details,
                    c.course_type,
                    s.id as state_id,
                    s.name as state_name,
                    d.id as district_id,
                    d.name as district_name,
                    b.id as block_id,
                    b.name as block_name,
                    oc.*
                FROM 
                    tbl_organizations_course oc
                JOIN tbl_organizations o ON o.id = oc.organization_id
                JOIN tbl_courses c ON c.id = oc. course_id
                JOIN tbl_states s ON s.id = o.state_id
                JOIN tbl_districts d ON d.id = o.district_id
                JOIN tbl_blocks b ON b.id = o.block_id
                WHERE oc.id = ? 
                ";

        return $this->db->query($sql, [$id])->getRow();
    }

    /**
     * Get organization banner
     * 
     * @param int $id
     * @return array[object]
     */
    public function OrganizationBanner($id = NULL)
    {
        return $this->db->table('tbl_organization_banners')
            ->select('tbl_organization_banners.id,tbl_organization_banners.banner_image')
            ->where('tbl_organization_banners.organization_id', $id)
            ->where('tbl_organization_banners.is_active', '1')
            ->get()
            ->getResult();
    }

    /**
     * Get Service banner
     * 
     * @param int $id
     * @return array[object]
     */
    public function ServiceBanner($id = NULL)
    {
        return $this->db->table('tbl_service_banners')
            ->select('tbl_service_banners.id,tbl_service_banners.banner_image')
            ->where('tbl_service_banners.service_id', $id)
            ->where('tbl_service_banners.is_active', '1')
            ->get()
            ->getResult();
    }

    /**
     * Get Dashboard banner
     * 
     * @param int $id
     * @return array[object]
     */
    public function getDashboardBanner($id = NULL)
    {
        return $this->db->table('tbl_dashboard_banners')
            ->select('tbl_dashboard_banners.id,tbl_dashboard_banners.banner, tbl_dashboard_banners.title')
            ->where('tbl_dashboard_banners.role_id', $id)
            ->where('tbl_dashboard_banners.is_active', '1')
            ->get()
            ->getResult();
    }

    /**
     * Get Order history
     * 
     * @param int $page
     * @param int $id
     * @return array[object]
     */
    public function Orderhistory($page, $id)
    {
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT 
                    e.id,
                    e.enquiry_number,
                    e.status,
                    e.cancel_reason,
                    e.created_at,
                    s.service_name
                FROM tbl_enquires e
                JOIN tbl_services s ON s.id = e.service_id 
                WHERE e.created_by = ? 
                ORDER BY e.id DESC
                LIMIT ? OFFSET ? ";

        return $this->db->query($sql, [$id, $limit, $offset])->getResult();
    }

    /**
     * Get count of order history
     * 
     * @param int $id
     * @return object 
     */
    public function getOrderCount($id)
    {
        $sql = "SELECT 
                    COUNT(id) as total_count
                FROM 
                    tbl_enquires
                WHERE created_by = ?";

        return $this->db->query($sql, [$id])->getRow();
    }

    /**
     * Get Banner types
     * 
     * @return array[object]
     */
    public function getBannerType()
    {
        return $this->db->table('tbl_banner_types')
            ->get()
            ->getResult();
    }

    /**
     * Get banner by type 
     * 
     * @param string $type
     * @return array[object]
     */
    public function getBanner($type)
    {
        $sql = "SELECT
                    bt.type,
                    b.*
                FROM 
                    tbl_banners b
                JOIN tbl_banner_types bt ON bt.id = b.type_id
                WHERE bt.type = ? ";

        return $this->db->query($sql, [$type])->getResult();
    }
}
