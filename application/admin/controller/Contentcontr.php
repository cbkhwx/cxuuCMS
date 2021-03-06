<?php

namespace app\admin\controller;

use app\admin\model\Content;
use app\admin\model\ContentContent;
use app\admin\model\Channel;
use app\admin\model\AdminLog;

class Contentcontr extends \think\Controller
{

    protected $middleware = ['Auth'];

    public function index()
    {
        $searchInfo = $this->request->param();
        error_reporting(E_ALL & ~E_NOTICE); //屏蔽未定义变量错误提示		
        $channelId = $searchInfo['cid'];
        $status = $searchInfo['status'];
        $title = $searchInfo['title'];
        $query = [];
        //增加同级审核 的审看权限 不受用户ID限制
        if (getCxuuGroupId() != 1 && empty(getCxuuBaseAuth("Content_examine"))) {
            array_push($query, ['user_id', '=', getCxuuUserId()]);
        }
        if (getCxuuGroupId() != 1 && !empty(getCxuuBaseAuth("Content_examine")) && empty($channelId)) {
            array_push($query, ['cid', 'in', getCxuuChannelAuth()]);
        }
        if ($status != '') {
            array_push($query, ['status', '=', $status]);
        }
        if (!empty($title)) {
            array_push($query, ['title', 'like', '%' . $title . '%']);
        }
        if (!empty($channelId)) {
            array_push($query, ['cid', '=', $channelId]);
        }
        //根据条件列表
        $list = Content::where($query)
            ->order('id', 'desc')
            ->paginate(20, false, ['query' => $searchInfo]);
        $page = $list->render();
        $channelTree = Channel::getCTree();
        $this->assign('channeltree', $channelTree);
        $this->assign('channelId', $channelId);
        $this->assign('list', $list);
        $this->assign('page', $page);
        return $this->fetch();
    }

    public function addedit()
    {
        $getInfo = $this->request->param();
        $channelList = Channel::select();
        $channelTree = Channel::getCTree();
        $userInfo = getCxuuCookie();
        $this->assign('contr', $this->request->controller() . '/' . $this->request->action()); //传递给上传器 控制器及方法名称
        $this->assign('channellist', $channelList);
        $this->assign('userinfo', $userInfo);
        $this->assign('channeltree', $channelTree);
        if (empty($getInfo['id'])) {
            error_reporting(E_ALL & ~E_NOTICE); //屏蔽未定义变量错误提示
            $this->assign('constname', "添加");
            $this->assign('actionname', "addAction");
            $this->assign('getcid', $getInfo['cid']);
            return $this->fetch('content');
        } else {
            error_reporting(E_ALL & ~E_NOTICE); //屏蔽未定义变量错误提示
            if ($userInfo['group_id'] == 1 || !empty(getCxuuBaseAuth("Content_examine"))) {
                //超级管理员和有审查权限的用户组 不受限制
                $find = Content::where('id', $getInfo['id'])->find();
            }else {
                $find = Content::where('id', $getInfo['id'])->where('user_id', getCxuuUserId())->find();
            }
            if ($find) {
                $this->assign('info', $find);
                $this->assign('constname', "编辑");
                $this->assign('actionname', "editAction");
                $this->assign('getcid', $getInfo['cid']);
                return $this->fetch('content');
            } else {
                return '你没有权限编辑此条信息';
            }
        }
    }

    public function addAction()
    {
        $addPost = $this->request->post();
        $validate = new \app\admin\validate\Content;
        if (!$validate->check($addPost)) {
            return ajax_Jsonreport($validate->getError(), 0);
        }
        $addPost['created_date'] = time();
        if ($addPost['image'] != '') {
            $addPost['imageBL'] = 1; //如果有图片，则判断字段为1,利于SQL优化
        }
        $userInfo = getCxuuCookie();// 实例化 用户信息模型
        $addPost['user_id'] = $userInfo['user_id'];
        $addPost['usergroupname'] = $userInfo['groupname'];
        $add = Content::create($addPost);
        $add->ContentContent()->save(['content' => $addPost['content']]);//关联写入
        if ($add) {
            //写入日志
            $ip = $this->request->ip();
            $model = new AdminLog;
            $model->addData(time(), $userInfo['username'], $ip, $this->request->url(), "添加内容 ID为 " . $add->id);
            return ajax_Jsonreport("添加成功", 1, "/admin/contentcontr");
        } else {
            return ajax_Jsonreport("添加失败", 0);
        }
    }

    public function editAction()
    {
        $id = $this->request->post('id');
        if ($id) {
            $editPost = $this->request->post();
            $editPost['edited_date'] = time();
            if (empty($editPost['position_a'])) {
                $editPost['position_a'] = 0;
            }
            if (empty($editPost['position_b'])) {
                $editPost['position_b'] = 0;
            }
            if (empty($editPost['position_c'])) {
                $editPost['position_c'] = 0;
            }
            if ($editPost['image'] != '') {
                $editPost['imageBL'] = 1; //如果有图片，则判断字段为1,利于SQL优化
            } else {
                $editPost['imageBL'] = 0;
            }
            $edit = Content::get($id);
            $edit->save($editPost, ['id' => $id]);
            $edit->ContentContent->save(['content' => $editPost['content']]);//关联更新
            if ($edit) {
                //写入日志
                $ip = $this->request->ip();
                $userInfo = getCxuuCookie();// 实例化 用户信息模型
                $model = new AdminLog;
                $model->addData(time(), $userInfo['username'], $ip, $this->request->url(), "修改内容 ID为 " . $id);
                return ajax_Jsonreport("修改成功", 1, "/admin/contentcontr");
            } else {
                return ajax_Jsonreport("修改失败", 0);
            }
        }
    }

    public function Delete()
    {
        $id = $this->request->get('id');
        $userInfo = getCxuuCookie();
        if ($userInfo['group_id'] == 1) {
            //超级管理员不受限制
            $del = Content::where('id', $id)->delete();
            $delcontent = ContentContent::where('aid', $id)->delete();
        } else {
            $del = Content::where('id', $id)->where('user_id', $userInfo['user_id'])->delete();
            if ($del) {
                $delcontent = ContentContent::where('aid', $id)->where('user_id', $userInfo['user_id'])->delete();
            } else {
                return ajax_Jsonreport("你不能删除不属于你的内容", 0);
            }
        }
        if ($del && $delcontent) {
            //写入日志
            $ip = $this->request->ip();
            $model = new AdminLog;
            $model->addData(time(), $userInfo['username'], $ip, $this->request->url(), "删除内容 ID为 " . $id);
            return ajax_Jsonreport("删除成功", 1);
        } else {
            return ajax_Jsonreport("删除失败或你不能删除不属于你的内容", 0);
        }
    }

    //批量操作
    public function batchOperation()
    {
        $content = $this->request->param();
        $ip = $this->request->ip();
        $userInfo = getCxuuCookie();// 实例化 用户信息模型
        $model = new AdminLog;
        $status = $content["status"];
        $id = $content["id"];
        $cid = $content["cid"];
        $id_array = explode(',', $id);
        switch ($status) {
            case 1:
                //转移栏目
                foreach ($id_array as $value) {
                    $Operation = Content::get($value);
                    $Operation->cid = $cid;
                    $Operation->save();
                }
                //写入日志
                $model->addData(time(), $userInfo['username'], $ip, $this->request->url(), "转移栏目 ID为 " . $id);
                return ajax_Jsonreport("转移成功", 1);
                break;
            case 2:
                //审核
                foreach ($id_array as $value) {
                    $Operation = Content::get($value);
                    $Operation->status = 1;
                    $Operation->save();
                }
                //写入日志
                $model->addData(time(), $userInfo['username'], $ip, $this->request->url(), "批量审核 ID为 " . $id);
                return ajax_Jsonreport("批量审核成功", 1);
                break;
            case 3:
                //取消审核
                foreach ($id_array as $value) {
                    $Operation = Content::get($value);
                    $Operation->status = 0;
                    $Operation->save();
                }
                //写入日志
                $model->addData(time(), $userInfo['username'], $ip, $this->request->url(), "批量取消审核 ID为 " . $id);
                return ajax_Jsonreport("批量取消审核成功", 1);
                break;
            case 4:
                //删除
                $Operation = Content::destroy($id);
                $OperationContent = ContentContent::destroy($id);
                if ($Operation && $OperationContent) {
                    //写入日志
                    $model->addData(time(), $userInfo['username'], $ip, $this->request->url(), "批量删除 ID为 " . $id);
                    return ajax_Jsonreport("批量删除成功", 1);
                } else {
                    return ajax_Jsonreport("批量删除失败", 0);
                }
                break;
        }

    }

}
