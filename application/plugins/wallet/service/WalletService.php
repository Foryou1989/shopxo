<?php
// +----------------------------------------------------------------------
// | ShopXO 国内领先企业级B2C免费开源电商系统
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2019 http://shopxo.net All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Devil
// +----------------------------------------------------------------------
namespace app\plugins\wallet\service;

use think\Db;
use app\service\PluginsService;
use app\service\ResourcesService;
use app\service\PaymentService;

/**
 * 钱包服务层
 * @author   Devil
 * @blog     http://gong.gg/
 * @version  0.0.1
 * @datetime 2016-12-01T21:51:08+0800
 */
class WalletService
{
    // 钱包状态
    public static $wallet_status_list = [
        0 => ['value' => 0, 'name' => '正常', 'checked' => true],
        1 => ['value' => 1, 'name' => '异常'],
        2 => ['value' => 2, 'name' => '已注销'],
    ];

    // 业务类型
    public static $business_type_list = [
        0 => ['value' => 0, 'name' => '系统', 'checked' => true],
        1 => ['value' => 1, 'name' => '充值'],
        2 => ['value' => 2, 'name' => '提现'],
        3 => ['value' => 3, 'name' => '消费'],
    ];

    // 操作类型
    public static $operation_type_list = [
        0 => ['value' => 0, 'name' => '减少', 'checked' => true],
        1 => ['value' => 1, 'name' => '增加'],
    ];

    // 金额类型
    public static $money_type_list = [
        0 => ['value' => 0, 'name' => '有效', 'checked' => true],
        1 => ['value' => 1, 'name' => '冻结'],
        2 => ['value' => 2, 'name' => '赠送'],
    ];

    /**
     * 钱包列表
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2019-04-30T00:13:14+0800
     * @param   [array]          $params [输入参数]
     */
    public static function WalletList($params = [])
    {
        $where = empty($params['where']) ? [] : $params['where'];
        $m = isset($params['m']) ? intval($params['m']) : 0;
        $n = isset($params['n']) ? intval($params['n']) : 10;
        $field = empty($params['field']) ? '*' : $params['field'];
        $order_by = empty($params['order_by']) ? 'id desc' : $params['order_by'];

        // 获取数据列表
        $data = Db::name('PluginsWallet')->field($field)->where($where)->limit($m, $n)->order($order_by)->select();
        if(!empty($data))
        {
            $wallet_status_list = WalletService::$wallet_status_list;
            foreach($data as &$v)
            {
                // 用户信息
                $v['user'] = self::GetUserInfo($v['user_id']);

                // 状态
                $v['status_text'] = (isset($v['status']) && isset($wallet_status_list[$v['status']])) ? $wallet_status_list[$v['status']]['name'] : '未知';

                // 创建时间
                $v['add_time_text'] = empty($v['add_time']) ? '' : date('Y-m-d H:i:s', $v['add_time']);
            }
        }
        return DataReturn('处理成功', 0, $data);
    }

    /**
     * 获取用户信息
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-05-05
     * @desc    description
     * @param   [int]          $user_id [用户id]
     */
    private static function GetUserInfo($user_id)
    {
        $user = Db::name('User')->field('username,nickname,mobile,email,avatar')->find($user_id);
        if(!empty($user))
        {
            $user['user_name_view'] = $user['username'];
            if(empty($user['user_name_view']))
            {
                $user['user_name_view'] = $user['nickname'];
            }
            if(empty($user['user_name_view']))
            {
                $user['user_name_view'] = $user['mobile'];
            }
            if(empty($user['user_name_view']))
            {
                $user['user_name_view'] = $user['email'];
            }

            // 头像
            if(!empty($user['avatar']))
            {
                $user['avatar'] = ResourcesService::AttachmentPathViewHandle($user['avatar']);
            } else {
                $user['avatar'] = config('shopxo.attachment_host').'/static/index/'.strtolower(config('DEFAULT_THEME', 'default')).'/images/default-user-avatar.jpg';
            }
        }

        return $user;
    }

    /**
     * 钱包总数
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-29
     * @desc    description
     * @param   [array]          $where [条件]
     */
    public static function WalletTotal($where = [])
    {
        return (int) Db::name('PluginsWallet')->where($where)->count();
    }

    /**
     * 钱包条件
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-29
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function WalletWhere($params = [])
    {
        $where = [];

        // 用户
        if(!empty($params['keywords']))
        {
            $user_ids = Db::name('User')->where('username|nickname|mobile|email', '=', $params['keywords'])->column('id');
            if(!empty($user_ids))
            {
                $where[] = ['user_id', 'in', $user_ids];
            } else {
                // 无数据条件，避免用户搜索条件没有数据造成的错觉
                $where[] = ['id', '=', 0];
            }
        }

        // 状态
        if(isset($params['status']) && $params['status'] > -1)
        {
            $where[] = ['status', '=', $params['status']];
        }

        return $where;
    }

    /**
     * 用户钱包
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-04-30
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function UserWallet($params = [])
    {
        // 请求参数
        $p = [
            [
                'checked_type'      => 'empty',
                'key_name'          => 'user',
                'error_msg'         => '用户信息有误',
            ],
        ];
        $ret = ParamsChecked($params, $p);
        if($ret !== true)
        {
            return DataReturn($ret, -1);
        }

        // 获取钱包, 不存在则创建
        $wallet = Db::name('PluginsWallet')->where(['user_id' => $params['user']['id']])->find();
        if(empty($wallet))
        {
            $data = [
                'user_id'       => $params['user']['id'],
                'status'        => 0,
                'add_time'      => time(),
            ];
            $wallet_id = Db::name('PluginsWallet')->insertGetId($data);
            if($wallet_id > 0)
            {
                $wallet = Db::name('PluginsWallet')->find($wallet_id);
            } else {
                return DataReturn('钱包添加失败', -100);
            }
        }

        return DataReturn('操作成功', 0, $wallet);
    }

    /**
     * 钱包日志添加
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2019-05-07T00:57:36+0800
     * @param   [array]          $params [输入参数]
     * @return  [boolean]                [成功true, 失败false]
     */
    public static function WalletLogInsert($params = [])
    {
        $data = [
            'user_id'           => isset($params['user_id']) ? intval($params['user_id']) : 0,
            'wallet_id'         => isset($params['wallet_id']) ? intval($params['wallet_id']) : 0,
            'business_type'     => isset($params['business_type']) ? intval($params['business_type']) : 0,
            'operation_type'    => isset($params['operation_type']) ? intval($params['operation_type']) : 0,
            'money_type'        => isset($params['money_type']) ? intval($params['money_type']) : 0,
            'money'             => isset($params['money']) ? PriceNumberFormat($params['money']) : 0.00,
            'msg'               => empty($params['msg']) ? '系统' : $params['msg'],
            'add_time'          => time(),
        ];
        return Db::name('PluginsWalletLog')->insertGetId($data) > 0;
    }

    /**
     * 钱包编辑
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-05-06
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function WalletEdit($params = [])
    {
        // 请求参数
        $p = [
            [
                'checked_type'      => 'empty',
                'key_name'          => 'id',
                'error_msg'         => '钱包id有误',
            ],
            [
                'checked_type'      => 'in',
                'key_name'          => 'status',
                'checked_data'      => array_column(self::$wallet_status_list, 'value'),
                'error_msg'         => '钱包状态有误',
            ],
            [
                'checked_type'      => 'fun',
                'key_name'          => 'normal_money',
                'checked_data'      => 'CheckPrice',
                'is_checked'        => 1,
                'error_msg'         => '有效金额格式有误',
            ],
            [
                'checked_type'      => 'fun',
                'key_name'          => 'frozen_money',
                'checked_data'      => 'CheckPrice',
                'is_checked'        => 1,
                'error_msg'         => '冻结金额格式有误',
            ],
            [
                'checked_type'      => 'fun',
                'key_name'          => 'give_money',
                'checked_data'      => 'CheckPrice',
                'is_checked'        => 1,
                'error_msg'         => '赠送金额格式有误',
            ],
        ];
        $ret = ParamsChecked($params, $p);
        if($ret !== true)
        {
            return DataReturn($ret, -1);
        }

        // 获取钱包
        $wallet = Db::name('PluginsWallet')->find(intval($params['id']));
        if(empty($wallet))
        {
            return DataReturn('钱包不存在或已删除', -10);
        }

        // 开始处理
        Db::startTrans();

        // 数据
        $data = [
            'status'        => intval($params['status']),
            'normal_money'  => empty($params['normal_money']) ? 0.00 : PriceNumberFormat($params['normal_money']),
            'frozen_money'  => empty($params['frozen_money']) ? 0.00 : PriceNumberFormat($params['frozen_money']),
            'give_money'    => empty($params['give_money']) ? 0.00 : PriceNumberFormat($params['give_money']),
            'upd_time'      => time(),
        ];
        if(!Db::name('PluginsWallet')->where(['id'=>$wallet['id']])->update($data))
        {
            Db::rollback();
            return DataReturn('编辑失败', -100);
        }

        // 日志
        // 字段名称 金额类型
        $money_field = [
            ['field' => 'normal_money', 'money_type' => 0],
            ['field' => 'frozen_money', 'money_type' => 1],
            ['field' => 'give_money', 'money_type' => 2],
        ];
        foreach($money_field as $v)
        {
            // 有效金额
            if($wallet[$v['field']] != $data[$v['field']])
            {
                $log_data = [
                    'user_id'           => $wallet['user_id'],
                    'wallet_id'         => $wallet['id'],
                    'business_type'     => 0,
                    'operation_type'    => ($wallet[$v['field']] < $data[$v['field']]) ? 1 : 0,
                    'money_type'        => $v['money_type'],
                    'money'             => ($wallet[$v['field']] < $data[$v['field']]) ? PriceNumberFormat($data[$v['field']]-$wallet[$v['field']]) : PriceNumberFormat($wallet[$v['field']]-$data[$v['field']]),
                    'msg'               => '管理员操作',
                ];
                if(!self::WalletLogInsert($log_data))
                {
                    Db::rollback();
                    return DataReturn('日志添加失败', -101);
                }
            }
        }

        // 处理成功
        Db::commit();
        return DataReturn('编辑成功', 0);
    }
}
?>