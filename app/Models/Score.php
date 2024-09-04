<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Score extends Model
{
    use HasFactory;
    protected $table = 'scores';    //テーブル名のセット
    protected $primaryKey = 'id';
    protected $guarded = array();   //id以外は、更新可能create fill update
}
