<?php

namespace app\index\model;

use think\Model;

class Documents extends Model {

    protected $pk = 'id';
    // 定义全局的查询范围
    protected $globalScope = ['status'];
    public function scopeStatus($query)
    {
        $query->where('status',1);
    }
/*	public function ContentContent()
    {
        return $this->hasOne('ContentContent','aid','id');
    }*/

}
