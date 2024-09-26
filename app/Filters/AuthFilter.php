<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\StudentModel;
use UnexpectedValueException;

class AuthFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     * By default it should not return anything during
     * normal execution. However, when an abnormal state
     * is found, it should return an instance of
     * CodeIgniter\HTTP\Response. If it does, script
     * execution will end and that Response will be
     * sent back to the client, allowing for error pages,
     * redirects, etc.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $bearerTokenHeader = $request->header('Authorization');

        if (is_null($bearerTokenHeader)) {
            header('Content-Type:application/json');
            http_response_code(401);
            echo json_encode([
                'message' => 'authentication required',
            ]);
            die;
        }

        $token = getBearerToken($bearerTokenHeader);

        if (is_null($token)) {
            header('Content-Type:application/json');
            http_response_code(401);
            echo json_encode([
                'status' => false,
                'message' => 'authentication required',
                'type' => 'jwt'
            ]);
            die;
        }

        $jwtToken = $token;

        try {
            $tokenData = JWT::decode($jwtToken, new Key(getenv('JWT_SECRET'), 'HS256'));
        } catch (\Firebase\JWT\ExpiredException | UnexpectedValueException $e) {
            header('Content-Type:application/json');
            http_response_code(400);
            echo json_encode([
                'status' => false,
                'message' => $e->getMessage(),
                'type' => 'jwt'
            ]);
            die;
        }

        $user_id = $tokenData->user_id;

        $model = new StudentModel();
        $user = $model->findUserByUserId($user_id);

        // print_r($user); die;

        if (!is_null($user)) {
            setAuthUser($user);
        }

        return true;
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}
