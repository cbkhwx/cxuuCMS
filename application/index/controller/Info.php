<?php
/**
 * Created by 龙啸轩PHP 信息 管理系统.
 * User: 邓中华
 * Date: 2018/10/28
 * Time: 12:34
 */

namespace app\index\controller;

use app\index\model\Content;
use app\index\model\ContentContent;
use app\index\model\Channel;
use think\facade\Cache;

class Info extends \think\Controller
{

    public function _empty()
    {
        return error404();
    }

    public function index()
    {
        $id = request()->route('id');
        if (empty($id)) {
            return error404();
        }

        if (Cache::get("findOnContent" . $id)) {
            $cache = cache("findOnContent" . $id);
            //$cachecontent = cache("findOnContentContent".$id);
        } else {
            $cache = Content::where('id', $id)->find();
            if (empty($cache)) {
                return validateError("内容不存在，请勿非法操作");
            }

            $cache['content'] = ContentContent::where('aid', $id)->find();
            Cache::set("findOnContent" . $id, $cache, 20);
            //Cache::set("findOnContentContent".$id, $cachecontent,20);
        }

            Content::where('id', $id)->field('hits')->setInc('hits'); //阅读量递增

            $channelpath = Channel::getUrlTree($cache['cid']);
            $this->assign('channelpath', $channelpath);//获取栏目 树

            $this->assign('info', $cache);
            $this->assign('content', $cache['content']);
            return $this->fetch('content');

    }

}
