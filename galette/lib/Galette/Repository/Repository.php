<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Repositories
 *
 * PHP version 5
 *
 * Copyright © 2013-2014 The Galette Team
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
 * @category  Repository
 * @package   Galette
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-26
 */

namespace Galette\Repository;

use Analog\Analog;
use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Core\Login;

/**
 * Repositories
 *
 * @category  Repository
 * @name      Repository
 * @package   Galette
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2013-2014 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     Available since 0.7.5dev - 2013-02-26
 */
abstract class Repository
{
    protected $zdb;
    protected $preferences;
    protected $entity;
    protected $login;

    /**
     * Main constructor
     *
     * @param Db          $zdb         Database instance
     * @param Preferences $preferences Galette preferences
     * @param Login       $login       Logged in instance
     * @param string      $entity      Related entity class name
     */
    public function __construct(Zdb $zdb, Preferences $preferences, Login $login, $entity = null)
    {
        $this->zdb = $zdb;
        $this->preferences = $preferences;
        $this->login = $login;

        if ($entity === null) {
            //no entity class name provided. Take Repository
            //class name and remove trailing 's'
            $r = array_slice(explode('\\', get_class($this)), -1);
            $repo = $r[0];
            $ent = substr($repo, 0, -1);
            if ($ent != $repo) {
                $entity = $ent;
            } else {
                throw new \RuntimeException(
                    'Unable to find entity name from repository one. Please '.
                    'provide entity name in repository constructor'
                );
            }
        }
        $entity = 'Galette\\Entity\\' . $entity;
        if (class_exists($entity)) {
            $this->entity = $entity;
        } else {
            throw new \RuntimeException(
                'Entity class ' . $entity . ' cannot be found!'
            );
        }
    }

    /**
     * Get entity instance
     *
     * @return Object
     */
    public function getEntity()
    {
        $name = $this->entity;
        return new $name(
            $this->zdb,
            $this->preferences,
            $this->login
        );
    }

    /**
     * Get list
     *
     * @return Object[]
     */
    abstract public function getList();

    /**
     * Add default values in database
     *
     * @param boolean $check_first Check first if it seem initialized, defaults to true
     *
     * @return boolean
     */
    abstract public function installInit($check_first = true);
}
