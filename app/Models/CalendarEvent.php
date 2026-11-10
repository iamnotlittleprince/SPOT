<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CalendarEvent extends Model
{
    protected $fillable = ['user_id','project_id','title','description','starts_at','ends_at','provider','external_id','meeting_url','location','color','task_id','group_key'];
    protected function casts(): array { return ['starts_at'=>'datetime','ends_at'=>'datetime']; }
}
