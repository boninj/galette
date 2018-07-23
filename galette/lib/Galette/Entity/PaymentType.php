<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Payment type
 *
 * PHP version 5
 *
 * Copyright © 2018 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Entity
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.2dev - 2018-07-23
 */

namespace Galette\Entity;

use Galette\Core;
use Galette\Core\Db;
use Galette\Repository\PaymentTypes;
use Analog\Analog;
use Zend\Db\Sql\Expression;

/**
 * Payment type
 *
 * @category  Entity
 * @name      PaymentType
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2018 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.9.2dev - 2018-07-23
 */

class PaymentType
{
    const TABLE = 'paymenttypes';
    const PK = 'type_id';

    private $zdb;
    private $id;
    private $name;

    const OTHER = 0;
    const CASH = 1;
    const CREDITCARD = 2;
    const CHECK = 3;
    const TRANSFER = 4;
    const PAYPAL = 5;

    /**
     * Main constructor
     *
     * @param Db    $zdb  Database instance
     * @param mixed $args Arguments
     */
    public function __construct(Db $zdb, $args = null)
    {
        $this->zdb = $zdb;
        if (is_int($args)) {
            $this->load($args);
        } elseif ($args !== null && is_object($args)) {
            $this->loadFromRs($args);
        }
    }

    /**
     * Load a payment type from its identifier
     *
     * @param integer $id Identifier
     *
     * @return void
     */
    private function load($id)
    {
        try {
            $select = $this->zdb->select(self::TABLE);
            $select->limit(1)->where(self::PK . ' = ' . $id);

            $results = $zdb->execute($select);
            $res = $results->current();

            $this->id = $id;
            $this->name = $res->type_name;
        } catch (\Exception $e) {
            Analog::log(
                'An error occurred loading payment type #' . $id . "Message:\n" .
                $e->getMessage(),
                Analog::ERROR
            );
        }
    }

    /**
     * Load payment type from a db ResultSet
     *
     * @param ResultSet $rs ResultSet
     *
     * @return void
     */
    private function loadFromRs($rs)
    {
        $pk = self::PK;
        $this->id = $rs->$pk;
        $this->name = $rs->type_name;
    }

    /**
     * Store payment type in database
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function store($zdb)
    {
        $data = array(
            'type_name' => $this->name
        );
        try {
            if ($this->id !== null && $this->id > 0) {
                $update = $zdb->update(self::TABLE);
                $update->set($data)->where(
                    self::PK . '=' . $this->id
                );
                $zdb->execute($update);
            } else {
                $insert = $zdb->insert(self::TABLE);
                $insert->values($data);
                $add = $zdb->execute($insert);
                if (!$add->count() > 0) {
                    Analog::log('Not stored!', Analog::ERROR);
                    return false;
                }
            }
            return true;
        } catch (\Exception $e) {
            Analog::log(
                'An error occurred storing payment type: ' . $e->getMessage() .
                "\n" . print_r($data, true),
                Analog::ERROR
            );
            return false;
        }
    }

    /**
     * Remove current title
     *
     * @param Db $zdb Database instance
     *
     * @return boolean
     */
    public function remove($zdb)
    {
        $id = (int)$this->id;
        if (in_array($id, array_keys($this->getSystemTypes()))) {
            throw new \RuntimeException(_T("You cannot delete system payment types!"));
        }

        try {
            $delete = $zdb->delete(self::TABLE);
            $delete->where(
                self::PK . ' = ' . $id
            );
            $zdb->execute($delete);
            Analog::log(
                'Payment type #' . $id . ' (' . $this->name
                . ') deleted successfully.',
                Analog::INFO
            );
            return true;
        } catch (\RuntimeException $re) {
            throw $re;
        } catch (\Exception $e) {
            Analog::log(
                'Unable to delete payment type ' . $id . ' | ' . $e->getMessage(),
                Analog::ERROR
            );
            throw $e;
        }
    }

    /**
     * Getter
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        global $lang;

        switch ($name) {
            case 'id':
            case 'name':
                return $this->$name;
                break;
            default:
                Analog::log(
                    'Unable to get Title property ' . $name,
                    Analog::WARNING
                );
                break;
        }
    }

    /**
     * Setter
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'name':
                if (trim($value) === '') {
                    Analog::log(
                        'Trying to set empty value for payment type',
                        Analog::WARNING
                    );
                } else {
                    $this->$name = $value;
                }
                break;
            default:
                Analog::log(
                    'Unable to set property ' .$name,
                    Analog::WARNING
                );
                break;
        }
    }

    /**
     * Get system payment types
     *
     * @return array
     */
    public function getSystemTypes()
    {
        $systypes = [
            self::OTHER         => _T("Other"),
            self::CASH          => _T("Cash"),
            self::CREDITCARD    => _T("Credit card"),
            self::CHECK         => _T("Check"),
            self::TRANSFER      => _T("Transfer"),
            self::PAYPAL        => _T("Paypal")
        ];
        return $systypes;
    }
}
