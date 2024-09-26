<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS");
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Credentials: true');
    header("Access-Control-Allow-Headers: *");
    die;
}

header("Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: *");


$routes->post('register', 'Auth::register');
$routes->post('auth/txn-status', 'Auth::txnStatus');
$routes->post('login', 'Auth::login');

$routes->get('master/state', 'Master::getState');
$routes->post('master/district', 'Master::getDistrict');
$routes->post('master/block', 'Master::getBlock');
$routes->get('master/plan', 'Master::getPlan');
$routes->get('master/services', 'Master::getServices');
$routes->get('master/service-type', 'Master::getServiceType');
$routes->get('master/class', 'Master::getClass');
$routes->get('master/courses', 'Master::getCourses');
$routes->get('master/organization-course', 'Master::getOrganizationCourse');
$routes->get('master/course-details', 'Master::getCourseDetails');
$routes->get('master/contact-details', 'Master::getContactDetails');
$routes->get('master/banner-types', 'Master::getBannerType');
$routes->post('master/career-guidance-banner', 'Master::getCareerGuidanceBanner');
$routes->get('master/how-to-apply', 'Master::getApplyData');

/// New work report
$routes->get('master/organization-banner', 'Master::getOrganizationBanner');
$routes->get('master/service-banner', 'Master::getServiceBanner');
$routes->get('master/dashboard-banner', 'Master::getDashboardBanner', ['filter' => 'authFilter']);
$routes->get('master/order-history', 'Master::getOrderhistory', ['filter' => 'authFilter']);
/// New work report    

$routes->post('auth/forget-password', 'Auth::forgetPassword');
$routes->post('auth/forget-password/verify-otp', 'Auth::verifyOtp');
$routes->post('auth/reset-password', 'Auth::resetPassword');
$routes->post('auth/change-password', 'Auth::changePassword', ['filter' => 'authFilter']);

$routes->get('student/profile', 'Student::getProfile', ['filter' => 'authFilter']);
$routes->post('student/edit-profile', 'Student::editProfile', ['filter' => 'authFilter']);
$routes->get('student/order-details', 'Student::orderDetails', ['filter' => 'authFilter']);
$routes->get('student/notifications', 'Student::getNotifications', ['filter' => 'authFilter']);
$routes->get('student/notifications/count', 'Student::getNotificationCount', ['filter' => 'authFilter']);
$routes->get('student/notification/view', 'Student::notificationView', ['filter' => 'authFilter']);
$routes->post('student/query', 'Student::query', ['filter' => 'authFilter']);
$routes->get('student/buy-plan', 'Student::buyPlan', ['filter' => 'authFilter']);
$routes->post('student/buy-plan/status-check', 'Student::statusCheck', ['filter' => 'authFilter']);
$routes->post('student/form-submit', 'Student::formSubmit', ['filter' => 'authFilter']);
$routes->post('student/carrier-guidance', 'Student::carrierGuide', ['filter' => 'authFilter']);
$routes->post('student/external-link-records', 'Student::externalRecords', ['filter' => 'authFilter']);



$routes->get('home', 'Home::index');
