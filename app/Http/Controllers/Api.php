<?php

namespace App\Http\Controllers;

use App\Proxies\Models\ProxiesModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class Api extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function push(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'host'     => 'required',
            'port'     => 'required',
            'protocol' => 'required',
            'username' => 'nullable',
            'password' => 'nullable',
        ]);

        if ($validator->fails()) {
            return Response::json([
                'status' => -1,
                'msg'    => $validator->errors()->first(),
            ]);
        }

        if (ProxiesModel::query()
            ->where('protocol', $request->input('protocol'))
            ->where('host', $request->input('host'))
            ->where('port', $request->input('port'))->exists()) {
            return Response::json([
                'status' => -1,
                'msg'    => '代理地址重复',
            ]);
        }

        $data           = $validator->validated();
        $data['status'] = 0;
        ProxiesModel::create($data);
        return Response::json([
            'status' => 0,
            'msg'    => '提交成功',
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function queue(): JsonResponse
    {
        $count = ProxiesModel::where('status', 0)->count();
        return Response::json([
            'status' => 0,
            'data'   => [
                'count' => $count,
            ],
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function count(): JsonResponse
    {
        $count = ProxiesModel::where('status', 1)->count();
        return Response::json([
            'status' => 0,
            'data'   => [
                'count' => $count,
            ],
        ]);
    }

    /**
     * @return string
     */
    public function get(): string
    {
        $proxy = ProxiesModel::shift();
        return $proxy ? "{$proxy->protocol}://{$proxy->host}:{$proxy->port}" : '';
    }
}
