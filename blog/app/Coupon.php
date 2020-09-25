<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Coupon
 *
 * @property int $id
 * @property string $name
 * @property string $usage_type
 * @property int $value
 * @property int $min_value
 * @property string $time_type
 * @property string $expire_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Coupon newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Coupon newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Coupon query()
 * @method static \Illuminate\Database\Eloquent\Builder|Coupon whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Coupon whereExpireDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Coupon whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Coupon whereMinValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Coupon whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Coupon whereTimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Coupon whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Coupon whereUsageType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Coupon whereValue($value)
 * @mixin \Eloquent
 */
class Coupon extends Model
{
    const DISCOUNT = 'discount'; // скидка по проценту
    const FIX_SUM = 'fix'; // фиксировання скидка
    const DISPOSABLE = 'disposable'; // одноразовый
    const EXPIRE = 'expire'; // со сроком действия
    const ENDLESS = 'endless'; // без срока действия

    protected $fillable = [
        'name', // уникальное имя купона
        'products', // json список товаров
        'usage_type', // вид купона (discount, fix)
        'value', // значение купона (размер скидки в процентах или фиксированное число)
        'min_value', // минимальное значение суммы покупки для использования фиксированного купона
        'time_type', // вид купона по времени (disposable, expire, endless)
        'expire_time', // дата окончания срока действия (если есть)
    ];

    public function users()
    {
        return $this->belongsToMany('User');
    }
    
    // public function products()
    // {
    //     return $this->belongsToMany('Product');
    // }
}
