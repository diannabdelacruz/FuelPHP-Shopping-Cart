<?php

namespace Ecommerce;

/**
 * FuelPHP Shopping Cart class
 *
 * 
 *  @package  FuelPHP Shopping Cart class
 *  @version 1.0beta
 *  @author Patrik Gmitter
 *  @license MIT License
 *  @copyright 2011 Patrik Gmitter
 */
class Cart
{

    public static $product_id_rules = '\,\:\.a-z0-9_-';
    protected static $items = array(); // obsah kosika
    protected static $total_items = 0; // pocet produktov
    protected static $total_qty = 0; // pocet kusov spolu (z jedneho produktu mozete mat v kosiku viac kusov)
    protected static $total_price = 0; // cena spolu

    public static function _init()
    {
        $cart = \Session::get('fuel_cart');
        if ($cart !== NULL)
        {
            static::$items = $cart['items'];
            static::$total_items = $cart['total_items'];
            static::$total_qty = $cart['total_qty'];
            static::$total_price = $cart['total_price'];
        }
    }

    public static function items()
    {
        return static::$items;
    }

    public static function total_items()
    {
        return static::$total_items;
    }

    public static function total_qty()
    {
        return static::$total_qty;
    }

    public static function total_price()
    {
        return static::$total_price;
    }

    public static function item($row_id)
    {
        if (static::in_cart($row_id))
        {
            return array(
                'rowid' => $row_id,
                'id' => static::$items[$row_id]['id'],
                'qty' => static::$items[$row_id]['qty'],
                'price' => static::$items[$row_id]['price'],
                'options' => static::$items[$row_id]['options']
            );
        }

        return array();
    }

    public static function item_options($row_id)
    {
        if (isset(static::$items[$row_id]['options']))
        {
            return static::$items[$row_id]['options'];
        }

        return array();
    }

    public static function add($items = array())
    {
        if (!is_array($items) OR count($items) == 0) // boli vlozene data ? ak nie = chyba
        {
            return FALSE;
        }

        if (isset($items['id'])) // jednoduche pole = jeden produkt
        {
            if (static::_add($items) === TRUE)
            {
                static::_save_cart();
                return TRUE;
            }
        }
        else // multidimenzionalne pole = viac produktov
        {
            foreach ($items as $val)
            {
                if (is_array($val) AND isset($val['id']))
                {
                    if (static::_add($val) === TRUE)
                    {
                        static::_save_cart();
                        return TRUE;
                    }
                }
            }
        }

        return FALSE;
    }

    private static function _add($item = array())
    {
        if (!is_array($item) OR count($item) == 0) // boli vlozene data ? ak nie = chyba
        {
            return FALSE;
        }

        if (!is_numeric($item['qty']) OR $item['qty'] == 0) // ak je "qty" 0
        {
            return FALSE;
        }

        if (!isset($item['qty']) OR !isset($item['price']) OR !isset($item['id'])) // ak boli zaslane povinne polia "qty", "price", "id"
        {
            return FALSE;
        }

        $item['qty'] = trim(preg_replace('/([^0-9])/i', '', $item['qty']));  // "qty" musi byt cislo
        $item['qty'] = trim(preg_replace('/(^[0]+)/i', '', $item['qty'])); // zmazeme 0 zo zaciatku retazca v "qty"

        if (!preg_match("/^[" . static::$product_id_rules . "]+$/i", $item['id'])) // validacia produkt "id"
        {
            return FALSE;
        }

        $item['price'] = trim(preg_replace('/([^0-9\.])/i', '', $item['price'])); // zmazeme vsetko co nieje cislo alebo bodka v "price"
        $item['price'] = trim(preg_replace('/(^[0]+)/i', '', $item['price']));  // zmazeme 0 zo zaciatku retazca v "price"

        if (!is_numeric($item['price'])) //je cena naozaj cislo ?
        {
            return FALSE;
        }

        // vytvorime unikatny kluc pre kazdy produkt
        if (isset($item['options']) AND count($item['options']) > 0)
        {
            $row_id = md5($item['id'] . implode('', $item['options']));
        }
        else
        {
            $row_id = md5($item['id']);
        }

        if (static::in_cart($row_id)) // upravime existujucu polozku v kosiku
        {
            static::$items[$row_id]['qty'] += (int) $item['qty']; // increase qty
            static::$items[$row_id]['price'] = $item['price']; // update price
        }
        else // do kosika pridame novu polozku
        {
            unset(static::$items[$row_id]);
            static::$items[$row_id]['rowid'] = $row_id;
            foreach ($item as $key => $val)
            {
                static::$items[$row_id][$key] = $val;
            }
        }

        return TRUE;
    }

    public static function update($items = array())
    {
        if (!is_array($items) OR count($items) == 0) // boli vlozene data ? ak nie = chyba
        {
            return FALSE;
        }

        if (isset($items['rowid'])) // jednoduche pole = upravujeme jeden produkt 
        {

            if (static::_update($items) === TRUE)
            {
                static::_save_cart();
                return TRUE;
            }
        }
        else // viacrozmerne pole = upravujeme viac produktov
        {
            foreach ($items as $val)
            {
                if (is_array($val) AND isset($val['rowid']) AND isset($val['qty']))
                {
                    if (static::_update($val) === TRUE)
                    {
                        static::_save_cart();
                        return TRUE;
                    }
                }
            }
        }

        return FALSE;
    }

    private static function _update($item = array())
    {
        if (!isset($item['rowid']) OR !isset(static::$items[$item['rowid']])) // ak nieje nastaveny rowid tak sa jedna o nezmysel
        {
            return FALSE;
        }

        if (isset($item['qty']))
        {
            $item['qty'] = trim(preg_replace('/([^0-9])/i', '', $item['qty']));  // "qty" musi byt cislo
            $item['qty'] = trim(preg_replace('/(^[0]+)/i', '', $item['qty'])); // zmazeme 0 zo zaciatku retazca v "qty"
            if (is_numeric($item['qty']))
            {
                if ($item['qty'] == 0) // ak je hodnota 0 tak polozku zmazeme ak je polozka vacsia ako 0 tak updatujeme
                {
                    unset(static::$items[$item['rowid']]);
                    return TRUE;
                }
                static::$items[$item['rowid']]['qty'] = (int) $item['qty'];
            }
        }

        if (isset($item['price']))
        {
            $item['price'] = trim(preg_replace('/([^0-9\.])/i', '', $item['price'])); // zmazeme vsetko co nieje cislo alebo bodka v "price"
            $item['price'] = trim(preg_replace('/(^[0]+)/i', '', $item['price']));  // zmazeme 0 zo zaciatku retazca v "price"
            if (is_numeric($item['price']))
            {
                static::$items[$item['rowid']]['price'] = $item['price'];
            }
        }

        return TRUE;
    }

    public static function delete($row_id=NULL, $qty=TRUE)
    {
        if ($row_id != NULL and isset(static::$items[$row_id]['qty']) and static::$items[$row_id]['qty'])
        {
            // ak je pocet kusov 1 a menej || qty je nastavene na TRUE zmazeme tovar z kosika uplne
            if (static::$items[$row_id]['qty'] < 2 or $qty === TRUE)
            {
                unset(static::$items[$row_id]);
            }
            else
            {
                if (!is_numeric($qty) or $qty < 1)
                {
                    return FALSE;
                }

                static::$items[$row_id]['qty'] -= (int) $qty;
            }

            static::_save_cart();
            return TRUE;
        }

        return FALSE;
    }

    public static function destroy()
    {
        static::$items = array();
        static::$total_price = 0;
        static::$total_items = 0;
        static::$total_qty = 0;

        \Session::delete('fuel_cart');
    }

    protected static function _save_cart()
    {
        $total = 0;
        $total_qty = 0;

        foreach (static::$items as $key => $val)
        {
            if (!is_array($val) or !isset($val['price']) or !isset($val['qty']))  // uistime sa ci su zadane vsetky povinne polozky
            {
                continue;
            }
            $total += ($val['price'] * $val['qty']);
            $total_qty += $val['qty'];
        }

        // konecne sucty
        static::$total_items = count(static::$items);
        static::$total_qty = $total_qty;
        static::$total_price = $total;

        \Session::set('fuel_cart', array(
            'total_items' => static::$total_items,
            'total_qty' => static::$total_qty,
            'total_price' => static::$total_price,
            'items' => static::$items
        ));

        return TRUE;
    }

    public static function in_cart($row_id)
    {
        if (isset(static::$items[$row_id]) and static::$items[$row_id])
        {
            return static::$items[$row_id]['qty'];
        }

        return false;
    }

}