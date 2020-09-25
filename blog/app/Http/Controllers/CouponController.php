<?php

namespace App\Http\Controllers;

use App\Coupon;
use App\User;

class CouponController extends Controller
{
    /**Создание купона*/
    public function create($params)
    {
        $json = json_encode($params['products_id']);

        if (!$params['name']) {
            $name = time() . rand(1000, 9999);
        } elseif (Coupon::where('name', $params['name'])->get()) {
            return [
                'status' => false,
                'coupon' => $params['name'],
                'msg' => __('coupon.already_exists', ['name' => $params['name']])
            ];
            // 'Данное имя купона уже существует';
        } else {
            $name = $params['name'];
        }

        if ($params['min_value'] <= 0) {
            return [
                'status' => false,
                'coupon' => $params['name'],
                'msg' => __('coupon.min_value_more', ['name' => $params['name'], 'value' => 0])
            ];
        }

        if (strtotime($params['expire_date']) <= time()) {
            return [
                'status' => false,
                'coupon' => $params['name'],
                'msg' => __('coupon.wrong_expire_date', ['name' => $params['name']])
            ];
        }

        Coupon::create([
            'name' => $name,
            'products' => $json,
            'usage_type' => $params['usage_type'],
            'value' => $params['value'],
            'min_value' => $params['min_value'],
            'time_type' => $params['time_type'],
            'expire_time' => $params['expire_time'],
        ]);

        return [
            'status' => true,
            'coupon' => $params['name'],
            'msg' => __('coupon.successfully_created', ['name' => $params['name']])
        ];
    }

    /**Удаление купона*/
    public function delete($name)
    {
        $coupon = Coupon::where('name', $name);

        if (!$coupon) return [
            'status' => false,
            'coupon' => $name,
            'msg' => __('coupon.not_found', ['name' => $name])
        ];

        $coupon->delete();

        return [
            'status' => true,
            'coupon' => $name,
            'msg' => __('coupon.successfully_deleted', ['name' => $name])
        ];
    }

    /**Проверка купона перед оплатой*/
    public function check($params)
    {
        if (empty($params)) {
            return [
                'status' => false,
                'msg' => __('coupon.params_empty')
            ];
        }

        $coupon = Coupon::where('name', $params['name']);

        if ($function = $this->checkExist($coupon, $params)) return $function;

        if ($function = $this->checkTimeType($coupon, $params)) return $function;

        if ($function = $this->checkProducts($coupon, $params)) return $function;

        if ($function = $this->checkUsageType($coupon, $params)) return $function;

        if ($function = $this->checkUsedOrNo($coupon, $params)) return $function;

        return [
            'status' => true,
            'coupon' => $params['name'],
            'msg' => __('coupon.successfully_verified', ['name' => $params['name']])
        ];
    }

    /**Использование купона*/
    public function use($params)
    {
        $user = User::find($params['user_id']);
        $coupon = Coupon::find($params['coupon_id']);

        if ($coupon->time_type == Coupon::DISPOSABLE) {
            $coupon->delete();
            return [
                'status' => true,
                'coupon' => $params['name'],
                'msg' => __('coupon.successfully_used_and_deleted', ['name' => $params['name']])
            ];
        } else {
            $user->coupons()->attach($coupon);
            return [
                'status' => true,
                'coupon' => $params['name'],
                'msg' => __('coupon.successfully_used', ['name' => $params['name']])
            ];
        }
    }

    /**Изменение цены товаров после использования купона*/
    public function changePrice($params)
    {
        $coupon = Coupon::find($params['coupon_id']);

        if ($function = $this->checkExist($coupon, $params)) return $function;

        $products = $this->getDiscountProducts($coupon, $params);

        $products = $this->useDiscount($coupon, $products);

        return [
            'status' => false,
            'coupon' => $params['name'],
            'products' => $products,
            'msg' => __('coupon.successfully_used', ['name' => $params['name']])
        ];
    }

    /**На какие товары действует купон */
    private function getDiscountProducts($coupon, $params)
    {
        if (empty($coupon->products)) {
            $products = $params['products'];
        } else {
            //иначе берем массив товаров, на которые действует купон
            $coupon_products = json_decode($coupon->products);

            $products = [];
            $correct_products = [];
            $counter = 0;

            //определяем на какие товары из корзины распространяется купон
            foreach ($params['products'] as $product_id => $product_price) {

                //сравниваем товары из корзины и товары в купоне
                foreach ($coupon_products as $coupon_product) {

                    //если товар в корзине отсутствует в купоне, то пропускаем его
                    if ($product_id !== $coupon_product) continue;

                    //иначе загоняем товар в массив
                    $correct_products[$counter] = $coupon_product;
                    //делаем массив из подходящих, под  купон, товаров
                    $products[$correct_products[$counter]] = $product_price;
                }
                $counter++;
            }
        }
        return $products;
    }

    /**Использование скидки */
    private function useDiscount($coupon, $products)
    {
        //значение скидки
        $discount = $coupon->value;

        //изменяем обычную цену товара на цену со скидкой
        foreach($products as $product_name => $product_price) {

            // если купон по проценту, то от цены товара вычитается процент
            if ($coupon->usage_type == Coupon::DISCOUNT) {

                $final_price = $product_price * (1 - $discount / 100);
            } else {

                //если купон с фиксированным числом, то отнимаем это значение от цены товара
                $final_price = $product_price - $discount;
            }

            //организуем массив с товарами, которые прошли по скидке и соответстующими ценами
            $products[$product_name] = $final_price;
        }

        return $products;
    }

    /**Проверка: существует ли купон*/
    private function checkExist($coupon, $params)
    {
        if (!$coupon) {
            return [
                'status' => false,
                'coupon' => $params['name'],
                'msg' => __('coupon.not_found', ['name' => $params['name']])
            ];
        }
    }

    /**Проверка срока действия*/
    private function checkTimeType($coupon, $params)
    {
        if ($coupon->time_type == Coupon::EXPIRE) {
            $expire_time = strtotime($coupon->expire_time);

            if ($expire_time <= time()) {
                return [
                    'status' => false,
                    'coupon' => $params['name'],
                    'msg' => __('coupon.is_expired', ['name' => $params['name']])
                ];
            }
        }
    }

    /**Проверка: подходят ли товары для купона*/
    private function checkProducts($coupon, $params)
    {
        $coupon_products = json_decode($coupon->products);
        $coupon_products = array_flip($coupon_products);

        if (!array_intersect_key($params['products'], $coupon_products)) {
            return [
                'status' => false,
                'coupon' => $params['name'],
                'msg' => __("coupon.unsuitable_goods", ['name' => $params['name']])
            ];
        }
    }

    /**Проверка: подходит ли стоимость заказа для использования купона*/
    private function checkUsageType($coupon, $params)
    {
        if ($coupon->usage_type == Coupon::FIX_SUM && $params['amount'] < $coupon->min_value) {
            return [
                'status' => false,
                'coupon' => $params['name'],
                'msg' => __("coupon.min_value_more", ['name' => $params['name'], 'value' => $coupon->min_value])
            ];
        }
    }

    /**Проверка: использовал ли пользователь купон*/
    private function checkUsedOrNo($coupon, $params)
    {
        if (User::find($params['user_id'])->coupons()->where('id', $params['coupon_id'])->get()) {
            return [
                'status' => false,
                'coupon' => $params['name'],
                'msg' => __("coupon.already_used", ['name' => $params['name']])
            ];
        }
    }
}
