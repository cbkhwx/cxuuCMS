<?php
/***
* 龙啸轩网站管理系统 作者：邓中华 20181009
***/
namespace app\index\model;

use think\Model;

class Juleader extends Model {

    protected $pk = 'id';
	
	protected $globalScope = ['status'];
    public function scopeStatus($query)
    {
        $query->where('status',1);
    }

}
