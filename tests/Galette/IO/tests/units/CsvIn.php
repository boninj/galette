<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * CsvIn tests
 *
 * PHP version 5
 *
 * Copyright © 2020 The Galette Team
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
 * @category  Core
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2020-05-11
 */

namespace Galette\IO\test\units;

use atoum;
use Galette\Entity\Adherent;
use Galette\DynamicFields\DynamicField;

/**
 * CsvIn tests class
 *
 * @category  Core
 * @name      CsvIn
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2020 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2020-05-11
 */
class CsvIn extends atoum
{
    private $zdb;
    private $i18n;
    private $preferences;
    private $session;
    private $login;
    private $view;
    private $history;
    private $members_fields;
    private $members_form_fields;
    private $members_fields_cats;
    private $flash;
    private $flash_data;
    private $container;
    private $request;
    private $response;
    private $mocked_router;
    private $contents_table = null;

    /**
     * Set up tests
     *
     * @param string $testMethod Calling method
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->contents_table = null;
        $this->mocked_router = new \mock\Slim\Router();
        $this->calling($this->mocked_router)->pathFor = function ($name, $params) {
            return $name;
        };
        $this->zdb = new \Galette\Core\Db();
        $this->i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
        $this->preferences = new \Galette\Core\Preferences(
            $this->zdb
        );
        $this->session = new \RKA\Session();
        $this->login = new \Galette\Core\Login($this->zdb, $this->i18n);
        $this->history = new \Galette\Core\History($this->zdb, $this->login, $this->preferences);
        $flash_data = [];
        $this->flash_data = &$flash_data;
        $this->flash = new \Slim\Flash\Messages($flash_data);

        global $zdb, $i18n, $login, $hist;
        $zdb = $this->zdb;
        $i18n = $this->i18n;
        $login = $this->login;
        $hist = $this->history;

        $app = new \Slim\App(['router' => $this->mocked_router, 'flash' => $this->flash]);
        $container = $app->getContainer();
        /*$this->view = new \mock\Slim\Views\Smarty(
            rtrim(GALETTE_ROOT . GALETTE_TPL_SUBDIR, DIRECTORY_SEPARATOR),
            [
                'cacheDir' => rtrim(GALETTE_CACHE_DIR, DIRECTORY_SEPARATOR),
                'compileDir' => rtrim(GALETTE_COMPILE_DIR, DIRECTORY_SEPARATOR),
                'pluginsDir' => [
                    GALETTE_ROOT . 'includes/smarty_plugins'
                ]
            ]
        );
        $this->calling($this->view)->render = function ($response) {
            $response->getBody()->write('Atoum view rendered');
            return $response;
        };

        $this->view->addSlimPlugins($container->get('router'), '/');
        //$container['view'] = $this->view;*/
        $container['view'] = null;
        $container['zdb'] = $zdb;
        $container['login'] = $this->login;
        $container['session'] = $this->session;
        $container['preferences'] = $this->preferences;
        $container['logo'] = null;
        $container['print_logo'] = null;
        $container['plugins'] = null;
        $container['history'] = $this->history;
        $container['i18n'] = null;
        $container['fields_config'] = null;
        $container['lists_config'] = null;
        $container['l10n'] = null;
        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields.php';
        $this->members_fields = $members_fields;
        $container['members_fields'] = $this->members_fields;
        $members_form_fields = $members_fields;
        foreach ($members_form_fields as $k => $field) {
            if ($field['position'] == -1) {
                unset($members_form_fields[$k]);
            }
        }
        $this->members_form_fields = $members_form_fields;
        $container['members_form_fields'] = $this->members_form_fields;
        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields_cats.php';
        $this->members_fields_cats = $members_fields_cats;
        $container['members_fields_cats'] = $this->members_fields_cats;
        $this->container = $container;
        $this->request = $container->get('request');
        $this->response = $container->get('response');
    }

    /**
     * Tear down tests
     *
     * @param string $testMethod Calling method
     *
     * @return void
     */
    public function afterTestMethod($testMethod)
    {
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(DynamicField::TABLE);
        $this->zdb->execute($delete);
        //cleanup dynamic translations
        $delete = $this->zdb->delete(\Galette\Core\L10n::TABLE);
        $delete->where([
            'text_orig' => [
                'Dynamic choice field',
                'Dynamic date field',
                'Dynamic text field'
            ]
        ]);
        $this->zdb->execute($delete);

        if ($this->contents_table !== null) {
            $this->zdb->drop($this->contents_table);
        }
    }

    /**
     * Import text CSV file
     *
     * @param array   $fields         Fields name to use at import
     * @param string  $file_name      File name
     * @param array   $flash_messages Excpeted flash messages from doImport route
     * @param airay   $members_list   List of faked members data
     * @param integer $count_before   Count before insertions. Defaults to 0 if null.
     * @param integer $count_after    Count after insertions. Default to $count_before + count $members_list
     * @param array   $values         Textual values for dynamic choices fields
     *
     * @return void
     */
    private function doImportFileTest(
        array $fields,
        $file_name,
        array $flash_messages,
        array $members_list,
        $count_before = null,
        $count_after = null,
        array $values = []
    ) {
        if ($count_before === null) {
            $count_before = 0;
        }
        if ($count_after === null) {
            $count_after = $count_before + count($members_list);
        }

        $members = new \Galette\Repository\Members();
        $list = $members->getList();
        $this->integer($list->count())->isIdenticalTo(
            $count_before,
            print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1), true)
        );

        $model = $this->getModel($fields);

        //get csv model file to add data in
        $controller = new \Galette\Controllers\CsvController($this->container);
        $response = $controller->getImportModel($this->request, $this->response);
        $csvin = new \galette\IO\CsvIn($this->zdb);

        $this->integer($response->getStatusCode())->isIdenticalTo(200);
        $this->array($response->getHeaders())
            ->array['Content-Type']->isIdenticalTo(['text/csv'])
            ->array['Content-Disposition']->isIdenticalTo(['attachment;filename="galette_import_model.csv"']);

        $csvfile_model = $response->getBody()->__toString();
        $this->string($csvfile_model)
             ->isIdenticalTo("\"" . implode("\";\"", $fields) . "\"\r\n");

        $contents = $csvfile_model;
        foreach ($members_list as $member) {
            $amember = [];
            foreach ($fields as $field) {
                $amember[$field] = $member[$field];
            }
            $contents .= "\"" . implode("\";\"", $amember) . "\"\r\n";
        }

        $path = GALETTE_CACHE_DIR . $file_name;
        $this->integer(file_put_contents($path, $contents));
        $_FILES['new_file'] = [
            'error' => UPLOAD_ERR_OK,
            'name'      => $file_name,
            'tmp_name'  => $path,
            'size'      => filesize($path)
        ];
        $this->boolean($csvin->store($_FILES['new_file'], true))->isTrue();
        $this->boolean(file_exists($csvin->getDestDir() . $csvin->getFileName()))->isTrue();

        $post = [
            'import_file'   => $file_name
        ];

        $request = clone $this->request;
        $request = $request->withParsedBody($post);

        $response = $controller->doImports($request, $this->response);
        $this->integer($response->getStatusCode())->isIdenticalTo(301);
        $this->array($this->flash_data['slimFlash'])->isIdenticalTo($flash_messages);
        $this->flash->clearMessages();

        $members = new \Galette\Repository\Members();
        $list = $members->getList();
        $this->integer($list->count())->isIdenticalTo($count_after);

        if ($count_before != $count_after) {
            foreach ($list as $member) {
                $created = $members_list[$member->fingerprint];
                foreach ($fields as $field) {
                    if (property_exists($member, $field)) {
                        $this->variable($member->$field)->isEqualTo($created[$field]);
                    } else {
                        //manage dynamic fields
                        $matches = [];
                        if (preg_match('/^dynfield_(\d+)/', $field, $matches)) {
                            $adh = new Adherent($this->zdb, (int)$member->id_adh, ['dynamics' => true]);
                            $expected = [
                                [
                                    'item_id'       => $adh->id,
                                    'field_form'    => 'adh',
                                    'val_index'     => 1,
                                    'field_val'     => $created[$field]
                                ]
                            ];

                            $dfield = $adh->getDynamicFields()->getValues($matches[1]);
                            if (isset($dfield[0]['text_val'])) {
                                //choice, add textual value
                                $expected[0]['text_val'] = $values[$created[$field]];
                            }

                            $this->array($adh->getDynamicFields()->getValues($matches[1]))->isEqualTo($expected);
                        } else {
                            throw new \RuntimeException("Unknown field $field");
                        }
                    }
                }
            }
        }
    }

    /**
     * Test CSV import loading
     *
     * @return void
     */
    public function testImport()
    {
        $fields = ['nom_adh', 'ville_adh', 'bool_exempt_adh', 'fingerprint'];
        $file_name = 'test-import-atoum.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $members_list = $this->getMemberData1();
        $count_before = 0;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //missing name
        $file_name = 'test-import-atoum-noname.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                'Field nom_adh is required, but missing in row 3'
            ]
        ];

        $members_list = $this->getMemberData2NoName();
        $count_before = 10;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);
    }

    /**
     * Get CSV import model
     *
     * @param array $fields Fields list
     *
     * @return \Galette\Entity\ImportModel
     */
    protected function getModel($fields): \Galette\Entity\ImportModel
    {
        $model = new \Galette\Entity\ImportModel();
        $this->boolean($model->remove($this->zdb))->isTrue();

        $this->object($model->setFields($fields))->isInstanceOf('Galette\Entity\ImportModel');
        $this->boolean($model->store($this->zdb))->isTrue();
        $this->boolean($model->load())->isTrue();
        return $model;
    }

    /**
     * Test dynamic translation has been added properly
     *
     * @param string $text_orig Original text
     * @param string $lang      Lang text has been added in
     *
     * @return void
     */
    protected function checkDynamicTranslation($text_orig, $lang = 'fr_FR.utf8')
    {
        $langs = array_keys($this->i18n->langs);
        $select = $this->zdb->select(\Galette\Core\L10n::TABLE);
        $select->columns([
            'text_locale',
            'text_nref',
            'text_trans'
        ]);
        $select->where(['text_orig' => $text_orig]);
        $results = $this->zdb->execute($select);
        $this->integer($results->count())->isIdenticalTo(count($langs));

        foreach ($results as $result) {
            $this->boolean(in_array(str_replace('.utf8', '', $result['text_locale']), $langs))->isTrue();
            $this->integer((int)$result['text_nref'])->isIdenticalTo(1);
            $this->string($result['text_trans'])->isIdenticalTo(
                ($result['text_locale'] == 'fr_FR.utf8' ? $text_orig : '')
            );
        }
    }

    /**
     * Test import with dynamic fields
     *
     * @return void
     */
    public function testImportDynamics()
    {

        $field_data = [
            'form_name'         => 'adh',
            'field_name'        => 'Dynamic text field',
            'field_perm'        => DynamicField::PERM_USER_WRITE,
            'field_type'        => DynamicField::TEXT,
            'field_required'    => 1,
            'field_repeat'      => 1
        ];

        $df = DynamicField::getFieldType($this->zdb, $field_data['field_type']);

        $stored = $df->store($field_data);
        $error_detected = $df->getErrors();
        $warning_detected = $df->getWarnings();
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $df->getErrors() + $df->getWarnings()
            )
        );
        $this->array($error_detected)->isEmpty(implode(' ', $df->getErrors()));
        $this->array($warning_detected)->isEmpty(implode(' ', $df->getWarnings()));
        //check if dynamic translation has been added
        $this->checkDynamicTranslation($field_data['field_name']);

        $select = $this->zdb->select(DynamicField::TABLE);
        $select->columns(array('num' => new \Laminas\Db\Sql\Expression('COUNT(1)')));
        $result = $this->zdb->execute($select)->current();
        $this->integer((int)$result->num)->isIdenticalTo(1);

        $fields = ['nom_adh', 'ville_adh', 'dynfield_' . $df->getId(), 'fingerprint'];
        $file_name = 'test-import-atoum-dyn.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $members_list = $this->getMemberData1();
        foreach ($members_list as $fingerprint => &$data) {
            $data['dynfield_' . $df->getId()] = 'Dynamic field value for ' . $data['fingerprint'];
        }
        $count_before = 0;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //missing name
        //$fields does not change from previous
        $file_name = 'test-import-atoum-dyn-noname.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                'Field nom_adh is required, but missing in row 3'
            ]
        ];
        $members_list = $this->getMemberData2NoName();
        foreach ($members_list as $fingerprint => &$data) {
            $data['dynfield_' . $df->getId()] = 'Dynamic field value for ' . $data['fingerprint'];
        }

        $count_before = 10;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //missing required dynamic field
        //$fields does not change from previous
        $file_name = 'test-import-atoum-dyn-nodyn.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                'Missing required field Dynamic text field'
            ]
        ];
        $members_list = $this->getMemberData2();
        $i = 0;
        foreach ($members_list as $fingerprint => &$data) {
            //two lines without required dynamic field.
            $data['dynfield_' . $df->getId()] = (($i == 2 || $i == 5) ? '' :
                'Dynamic field value for ' . $data['fingerprint']);
            ++$i;
        }

        $count_before = 10;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //cleanup members and dynamic fields values
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $this->zdb->execute($delete);

        //new dynamic field, of type choice.
        $values = [
            'First value',
            'Second value',
            'Third value'
        ];
        $cfield_data = [
            'form_name'         => 'adh',
            'field_name'        => 'Dynamic choice field',
            'field_perm'        => DynamicField::PERM_USER_WRITE,
            'field_type'        => DynamicField::CHOICE,
            'field_required'    => 0,
            'field_repeat'      => 1,
            'fixed_values'      => implode("\n", $values)
        ];

        $cdf = DynamicField::getFieldType($this->zdb, $cfield_data['field_type']);

        $stored = $cdf->store($cfield_data);
        $error_detected = $cdf->getErrors();
        $warning_detected = $cdf->getWarnings();
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $cdf->getErrors() + $cdf->getWarnings()
            )
        );
        $this->array($error_detected)->isEmpty(implode(' ', $cdf->getErrors()));
        $this->array($warning_detected)->isEmpty(implode(' ', $cdf->getWarnings()));
        //check if dynamic translation has been added
        $this->checkDynamicTranslation($cfield_data['field_name']);

        $select = $this->zdb->select(DynamicField::TABLE);
        $select->columns(array('num' => new \Laminas\Db\Sql\Expression('COUNT(1)')));
        $result = $this->zdb->execute($select)->current();
        $this->integer((int)$result->num)->isIdenticalTo(2);

        $this->array($cdf->getValues())->isIdenticalTo($values);

        $fields = ['nom_adh', 'ville_adh', 'dynfield_' . $cdf->getId(), 'fingerprint'];
        $file_name = 'test-import-atoum-dyn-cdyn.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $members_list = $this->getMemberData1();
        foreach ($members_list as $fingerprint => &$data) {
            //two lines without required dynamic field.
            $data['dynfield_' . $cdf->getId()] = rand(0, 2);
        }

        $count_before = 0;
        $count_after = 10;

        $this->doImportFileTest(
            $fields,
            $file_name,
            $flash_messages,
            $members_list,
            $count_before,
            $count_after,
            $values
        );

        //cleanup members and dynamic fields values
        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $this->zdb->execute($delete);
        //cleanup dynamic choices table
        $this->contents_table = $cdf->getFixedValuesTableName($cdf->getId());

        //new dynamic field, of type date.
        $cfield_data = [
            'form_name'         => 'adh',
            'field_name'        => 'Dynamic date field',
            'field_perm'        => DynamicField::PERM_USER_WRITE,
            'field_type'        => DynamicField::DATE,
            'field_required'    => 0,
            'field_repeat'      => 1
        ];

        $cdf = DynamicField::getFieldType($this->zdb, $cfield_data['field_type']);

        $stored = $cdf->store($cfield_data);
        $error_detected = $cdf->getErrors();
        $warning_detected = $cdf->getWarnings();
        $this->boolean($stored)->isTrue(
            implode(
                ' ',
                $cdf->getErrors() + $cdf->getWarnings()
            )
        );
        $this->array($error_detected)->isEmpty(implode(' ', $cdf->getErrors()));
        $this->array($warning_detected)->isEmpty(implode(' ', $cdf->getWarnings()));
        //check if dynamic translation has been added
        $this->checkDynamicTranslation($cfield_data['field_name']);

        $select = $this->zdb->select(DynamicField::TABLE);
        $select->columns(array('num' => new \Laminas\Db\Sql\Expression('COUNT(1)')));
        $result = $this->zdb->execute($select)->current();
        $this->integer((int)$result->num)->isIdenticalTo(3);


        $fields = ['nom_adh', 'ville_adh', 'dynfield_' . $cdf->getId(), 'fingerprint'];
        $file_name = 'test-import-atoum-cdyn-date.csv';
        $flash_messages = [
            'success_detected' => ["File '$file_name' has been successfully imported :)"]
        ];
        $members_list = $this->getMemberData1();
        foreach ($members_list as $fingerprint => &$data) {
            //two lines without required dynamic field.
            $data['dynfield_' . $cdf->getId()] = $data['date_crea_adh'];
        }

        $count_before = 0;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);

        //Test with a bad date
        //$fields does not change from previous
        $file_name = 'test-import-atoum-cdyn-baddate.csv';
        $flash_messages = [
            'error_detected' => [
                'File does not comply with requirements.',
                '- Wrong date format (Y-m-d) for Dynamic date field!'
            ]
        ];
        $members_list = $this->getMemberData2();
        $i = 0;
        foreach ($members_list as $fingerprint => &$data) {
            //two lines without required dynamic field.
            $data['dynfield_' . $cdf->getId()] = (($i == 2 || $i == 5) ? '20200513' : $data['date_crea_adh']);
            ++$i;
        }

        $count_before = 10;
        $count_after = 10;

        $this->doImportFileTest($fields, $file_name, $flash_messages, $members_list, $count_before, $count_after);
    }

    /**
     * Get first set of member data
     *
     * @return array
     */
    private function getMemberData1()
    {
        return array(
            'FAKER_0' => array (
                'nom_adh' => 'Boucher',
                'prenom_adh' => 'Roland',
                'ville_adh' => 'Dumas',
                'cp_adh' => '61276',
                'adresse_adh' => '5, chemin de Meunier',
                'email_adh' => 'remy44@lopez.net',
                'login_adh' => 'jean36',
                'mdp_adh' => 'HM~OCSl[]UkZp%Y',
                'mdp_adh2' => 'HM~OCSl[]UkZp%Y',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => true,
                'bool_display_info' => false,
                'sexe_adh' => 1,
                'prof_adh' => 'Technicien géomètre',
                'titre_adh' => null,
                'ddn_adh' => '1914-03-22',
                'lieu_naissance' => 'Laurent-sur-Guyot',
                'pseudo_adh' => 'tgonzalez',
                'pays_adh' => null,
                'tel_adh' => '+33 8 93 53 99 52',
                'url_adh' => null,
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-03-09',
                'pref_lang' => 'br',
                'fingerprint' => 'FAKER_0',
            ),
            'FAKER_1' =>  array (
                'nom_adh' => 'Lefebvre',
                'prenom_adh' => 'François',
                'ville_adh' => 'Laine',
                'cp_adh' => '53977',
                'adresse_adh' => '311, rue de Costa',
                'email_adh' => 'astrid64@masse.fr',
                'login_adh' => 'olivier.pierre',
                'mdp_adh' => '.4y/J>yN_QUh7Bw@NW>)',
                'mdp_adh2' => '.4y/J>yN_QUh7Bw@NW>)',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => false,
                'sexe_adh' => 2,
                'prof_adh' => 'Conseiller relooking',
                'titre_adh' => null,
                'ddn_adh' => '1989-10-31',
                'lieu_naissance' => 'Collet',
                'pseudo_adh' => 'agnes.evrard',
                'pays_adh' => null,
                'tel_adh' => '0288284193',
                'url_adh' => 'https://leroux.fr/omnis-autem-suscipit-consequuntur-possimus-sint-iste-beatae.html',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2019-11-29',
                'pref_lang' => 'oc',
                'fingerprint' => 'FAKER_1',
            ),
            'FAKER_2' =>  array (
                'nom_adh' => 'Lemaire',
                'prenom_adh' => 'Georges',
                'ville_adh' => 'Teixeira-sur-Mer',
                'cp_adh' => '40141',
                'adresse_adh' => 'place Guillaume',
                'email_adh' => 'lefort.vincent@club-internet.fr',
                'login_adh' => 'josette46',
                'mdp_adh' => '(IqBaAIR',
                'mdp_adh2' => '(IqBaAIR',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 0,
                'prof_adh' => 'Assistant logistique',
                'titre_adh' => null,
                'ddn_adh' => '1935-09-07',
                'lieu_naissance' => 'Ponsboeuf',
                'pseudo_adh' => 'fgay',
                'pays_adh' => null,
                'tel_adh' => '+33 7 45 45 19 81',
                'url_adh' => null,
                'activite_adh' => true,
                'id_statut' => 8,
                'date_crea_adh' => '2019-02-03',
                'pref_lang' => 'uk',
                'fingerprint' => 'FAKER_2',
            ),
            'FAKER_3' =>  array (
                'nom_adh' => 'Paul',
                'prenom_adh' => 'Thibaut',
                'ville_adh' => 'Mallet-sur-Prevost',
                'cp_adh' => '50537',
                'adresse_adh' => '246, boulevard Daniel Mendes',
                'email_adh' => 'ihamel@pinto.fr',
                'login_adh' => 'josephine.fabre',
                'mdp_adh' => '`2LrQcb9Utgm=Y\\S$',
                'mdp_adh2' => '`2LrQcb9Utgm=Y\\S$',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 0,
                'prof_adh' => 'Aide à domicile',
                'titre_adh' => null,
                'ddn_adh' => '1961-09-17',
                'lieu_naissance' => 'Gomez',
                'pseudo_adh' => 'chauvin.guillaume',
                'pays_adh' => 'Hong Kong',
                'tel_adh' => '+33 5 48 57 32 28',
                'url_adh' => null,
                'activite_adh' => true,
                'id_statut' => 1,
                'date_crea_adh' => '2017-11-20',
                'pref_lang' => 'nb_NO',
                'fingerprint' => 'FAKER_3',
                'societe_adh' => 'Jacques',
                'is_company' => true,
            ),
            'FAKER_4' =>  array (
                'nom_adh' => 'Pascal',
                'prenom_adh' => 'Isaac',
                'ville_adh' => 'Jourdanboeuf',
                'cp_adh' => '93966',
                'adresse_adh' => '5, boulevard de Boucher',
                'email_adh' => 'valerie.becker@gmail.com',
                'login_adh' => 'lucie08',
                'mdp_adh' => '|%+wtMW{l',
                'mdp_adh2' => '|%+wtMW{l',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Bruiteur',
                'titre_adh' => null,
                'ddn_adh' => '1953-12-11',
                'lieu_naissance' => 'Foucher',
                'pseudo_adh' => 'sauvage.dorothee',
                'pays_adh' => 'Bangladesh',
                'tel_adh' => '+33 4 75 14 66 56',
                'url_adh' => null,
                'activite_adh' => false,
                'id_statut' => 9,
                'date_crea_adh' => '2018-08-16',
                'pref_lang' => 'en_US',
                'fingerprint' => 'FAKER_4',
            ),
            'FAKER_5' =>  array (
                'nom_adh' => 'Morvan',
                'prenom_adh' => 'Joseph',
                'ville_adh' => 'Noel',
                'cp_adh' => '05069',
                'adresse_adh' => 'place de Barthelemy',
                'email_adh' => 'claunay@tele2.fr',
                'login_adh' => 'marthe.hoarau',
                'mdp_adh' => '\'C?}vJAU>:-iE',
                'mdp_adh2' => '\'C?}vJAU>:-iE',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Opérateur du son',
                'titre_adh' => null,
                'ddn_adh' => '1938-05-11',
                'lieu_naissance' => 'Beguedan',
                'pseudo_adh' => 'andre.guillou',
                'pays_adh' => null,
                'tel_adh' => '09 26 70 06 55',
                'url_adh' => 'http://www.hoarau.fr/quis-neque-ducimus-quidem-et',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2018-09-28',
                'pref_lang' => 'ca',
                'fingerprint' => 'FAKER_5',
            ),
            'FAKER_6' =>  array (
                'nom_adh' => 'Lebreton',
                'prenom_adh' => 'Emmanuelle',
                'ville_adh' => 'Lefevre',
                'cp_adh' => '29888',
                'adresse_adh' => '98, rue Moulin',
                'email_adh' => 'zacharie77@ruiz.fr',
                'login_adh' => 'marianne.collin',
                'mdp_adh' => '=jG{wyE',
                'mdp_adh2' => '=jG{wyE',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Galeriste',
                'titre_adh' => null,
                'ddn_adh' => '2001-02-01',
                'lieu_naissance' => 'Berthelot',
                'pseudo_adh' => 'ferreira.rene',
                'pays_adh' => 'Tuvalu',
                'tel_adh' => '+33 (0)7 47 56 89 70',
                'url_adh' => null,
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2018-01-13',
                'pref_lang' => 'es',
                'fingerprint' => 'FAKER_6',
            ),
            'FAKER_7' =>  array (
                'nom_adh' => 'Maurice',
                'prenom_adh' => 'Capucine',
                'ville_adh' => 'Renaultdan',
                'cp_adh' => '59 348',
                'adresse_adh' => '56, avenue Grenier',
                'email_adh' => 'didier.emmanuel@tiscali.fr',
                'login_adh' => 'william.herve',
                'mdp_adh' => '#7yUz#qToZ\'',
                'mdp_adh2' => '#7yUz#qToZ\'',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Cintrier-machiniste',
                'titre_adh' => null,
                'ddn_adh' => '1984-04-17',
                'lieu_naissance' => 'Rolland',
                'pseudo_adh' => 'roger27',
                'pays_adh' => 'Antilles néerlandaises',
                'tel_adh' => '0922523762',
                'url_adh' => null,
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-02-13',
                'pref_lang' => 'br',
                'fingerprint' => 'FAKER_7',
                'societe_adh' => 'Mace',
                'is_company' => true,
            ),
            'FAKER_8' =>  array (
                'nom_adh' => 'Hubert',
                'prenom_adh' => 'Lucy',
                'ville_adh' => 'Lagarde',
                'cp_adh' => '22 829',
                'adresse_adh' => '3, rue Pénélope Marie',
                'email_adh' => 'zoe02@morvan.com',
                'login_adh' => 'bernard.agathe',
                'mdp_adh' => '@9di}eJyc"0s_d(',
                'mdp_adh2' => '@9di}eJyc"0s_d(',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 2,
                'prof_adh' => 'Facteur',
                'titre_adh' => null,
                'ddn_adh' => '2008-01-13',
                'lieu_naissance' => 'Ribeiro',
                'pseudo_adh' => 'julien.isabelle',
                'pays_adh' => 'Mexique',
                'tel_adh' => '0809527977',
                'url_adh' => null,
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2019-06-26',
                'pref_lang' => 'de_DE',
                'fingerprint' => 'FAKER_8',
            ),
            'FAKER_9' =>  array (
                'nom_adh' => 'Goncalves',
                'prenom_adh' => 'Corinne',
                'ville_adh' => 'LesageVille',
                'cp_adh' => '15728',
                'adresse_adh' => '18, rue de Pinto',
                'email_adh' => 'julien.clement@dbmail.com',
                'login_adh' => 'xavier.nicolas',
                'mdp_adh' => '<W0XdOj2Gp|@;W}gWh]',
                'mdp_adh2' => '<W0XdOj2Gp|@;W}gWh]',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Eleveur de volailles',
                'titre_adh' => null,
                'ddn_adh' => '2013-09-12',
                'lieu_naissance' => 'Breton',
                'pseudo_adh' => 'louis.pruvost',
                'pays_adh' => null,
                'tel_adh' => '+33 (0)6 80 24 46 58',
                'url_adh' => null,
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-08-09',
                'pref_lang' => 'br',
                'fingerprint' => 'FAKER_9',
            )
        );
    }

    /**
     * Get second set of member data
     * two lines without name.
     *
     * @return array
     */
    private function getMemberData2()
    {
        return array (
            'FAKER_0' => array (
                'nom_adh' => 'Goncalves',
                'prenom_adh' => 'Margot',
                'ville_adh' => 'Alves',
                'cp_adh' => '76254',
                'adresse_adh' => '43, impasse Maurice Imbert',
                'email_adh' => 'guillou.richard@yahoo.fr',
                'login_adh' => 'suzanne.mathieu',
                'mdp_adh' => 'Thihk2z0',
                'mdp_adh2' => 'Thihk2z0',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 2,
                'prof_adh' => 'Cueilleur de cerises',
                'titre_adh' => null,
                'ddn_adh' => '2020-04-24',
                'lieu_naissance' => 'Poulain-les-Bains',
                'pseudo_adh' => 'olivier.roux',
                'pays_adh' => 'République Dominicaine',
                'tel_adh' => '08 95 04 73 14',
                'url_adh' => null,
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-07-31',
                'pref_lang' => 'ca',
                'fingerprint' => 'FAKER_0',
            ),
            'FAKER_1' => array (
                'nom_adh' => 'Da Silva',
                'prenom_adh' => 'Augustin',
                'ville_adh' => 'Perrin-sur-Masson',
                'cp_adh' => '31519',
                'adresse_adh' => '154, place Boulay',
                'email_adh' => 'marc60@moreno.fr',
                'login_adh' => 'hoarau.maryse',
                'mdp_adh' => '\\9Si%r/FAmz.HE4!{Q\\',
                'mdp_adh2' => '\\9Si%r/FAmz.HE4!{Q\\',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 2,
                'prof_adh' => 'Séismologue',
                'titre_adh' => null,
                'ddn_adh' => '1988-06-26',
                'lieu_naissance' => 'Martel',
                'pseudo_adh' => 'hchevalier',
                'pays_adh' => 'Kiribati',
                'tel_adh' => '04 55 49 80 92',
                'url_adh' => 'http://www.leblanc.com/nemo-non-rerum-commodi-sequi-ut',
                'activite_adh' => true,
                'id_statut' => 1,
                'date_crea_adh' => '2020-06-02',
                'pref_lang' => 'fr_FR',
                'fingerprint' => 'FAKER_1',
            ),
            'FAKER_2' => array (
                'nom_adh' => 'Doe',
                'prenom_adh' => 'Laetitia',
                'ville_adh' => 'SimonBourg',
                'cp_adh' => '90351',
                'adresse_adh' => '147, chemin de Chauvet',
                'email_adh' => 'jean.joseph@pinto.fr',
                'login_adh' => 'marianne.bourgeois',
                'mdp_adh' => '[oT:"ExE',
                'mdp_adh2' => '[oT:"ExE',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 0,
                'prof_adh' => 'Porteur de hottes',
                'titre_adh' => null,
                'ddn_adh' => '2010-03-13',
                'lieu_naissance' => 'Gallet',
                'pseudo_adh' => 'abarre',
                'pays_adh' => 'Kirghizistan',
                'tel_adh' => '07 47 63 11 31',
                'url_adh' => 'https://www.jacques.com/fuga-voluptatem-tenetur-rem-possimus',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-10-28',
                'pref_lang' => 'ar',
                'fingerprint' => 'FAKER_2',
            ),
            'FAKER_3' => array (
                'nom_adh' => 'Cordier',
                'prenom_adh' => 'Olivier',
                'ville_adh' => 'Lacroixboeuf',
                'cp_adh' => '58 787',
                'adresse_adh' => '77, place Gilbert Perrier',
                'email_adh' => 'adelaide07@yahoo.fr',
                'login_adh' => 'riou.sebastien',
                'mdp_adh' => '%"OC/UniE46',
                'mdp_adh2' => '%"OC/UniE46',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => false,
                'sexe_adh' => 2,
                'prof_adh' => 'Oenologue',
                'titre_adh' => null,
                'ddn_adh' => '2010-10-08',
                'lieu_naissance' => 'Leger',
                'pseudo_adh' => 'frederique.bernier',
                'pays_adh' => null,
                'tel_adh' => '+33 2 50 03 01 12',
                'url_adh' => null,
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-08-14',
                'pref_lang' => 'ar',
                'fingerprint' => 'FAKER_3',
            ),
            'FAKER_4' => array (
                'nom_adh' => 'Robert',
                'prenom_adh' => 'Grégoire',
                'ville_adh' => 'Delannoy-sur-Mer',
                'cp_adh' => '41185',
                'adresse_adh' => '15, boulevard de Pierre',
                'email_adh' => 'normand.matthieu@orange.fr',
                'login_adh' => 'guilbert.louis',
                'mdp_adh' => 'y(,HodJF*j',
                'mdp_adh2' => 'y(,HodJF*j',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 2,
                'prof_adh' => 'Mannequin détail',
                'titre_adh' => null,
                'ddn_adh' => '1974-05-14',
                'lieu_naissance' => 'Barbe-sur-Laurent',
                'pseudo_adh' => 'stoussaint',
                'pays_adh' => 'Îles Mineures Éloignées des États-Unis',
                'tel_adh' => '+33 (0)1 30 50 01 54',
                'url_adh' => 'http://www.lemaitre.org/dolorum-recusandae-non-eum-non',
                'activite_adh' => true,
                'id_statut' => 3,
                'date_crea_adh' => '2018-12-05',
                'pref_lang' => 'it_IT',
                'fingerprint' => 'FAKER_4',
                'societe_adh' => 'Chretien Martineau S.A.',
                'is_company' => true,
            ),
            'FAKER_5' =>  array (
                'nom_adh' => 'Doe',
                'prenom_adh' => 'Charles',
                'ville_adh' => 'Charpentier-sur-Lebrun',
                'cp_adh' => '99129',
                'adresse_adh' => '817, chemin de Bonnin',
                'email_adh' => 'guillou.augustin@live.com',
                'login_adh' => 'dominique80',
                'mdp_adh' => '~g??E0HE$A>2"e*C7+Kw',
                'mdp_adh2' => '~g??E0HE$A>2"e*C7+Kw',
                'bool_admin_adh' => true,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 0,
                'prof_adh' => 'Commandant de police',
                'titre_adh' => null,
                'ddn_adh' => '2007-03-26',
                'lieu_naissance' => 'Boutin',
                'pseudo_adh' => 'virginie.jacquet',
                'pays_adh' => null,
                'tel_adh' => '0393209420',
                'url_adh' => null,
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2018-02-17',
                'pref_lang' => 'fr_FR',
                'fingerprint' => 'FAKER_5',
            ),
            'FAKER_6' => array (
                'nom_adh' => 'Thierry',
                'prenom_adh' => 'Louis',
                'ville_adh' => 'Henry',
                'cp_adh' => '98 144',
                'adresse_adh' => '383, avenue Éléonore Bouchet',
                'email_adh' => 'bernard.elodie@orange.fr',
                'login_adh' => 'ubreton',
                'mdp_adh' => 'lTBT@,hsE`co?C2=',
                'mdp_adh2' => 'lTBT@,hsE`co?C2=',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => false,
                'sexe_adh' => 2,
                'prof_adh' => 'Endocrinologue',
                'titre_adh' => null,
                'ddn_adh' => '1994-07-19',
                'lieu_naissance' => 'Pagesdan',
                'pseudo_adh' => 'diallo.sebastien',
                'pays_adh' => null,
                'tel_adh' => '+33 5 72 28 24 81',
                'url_adh' => null,
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-03-16',
                'pref_lang' => 'en_US',
                'fingerprint' => 'FAKER_6',
            ),
            'FAKER_7' =>  array (
                'nom_adh' => 'Delattre',
                'prenom_adh' => 'Susanne',
                'ville_adh' => 'Roche-les-Bains',
                'cp_adh' => '37 104',
                'adresse_adh' => '44, rue Suzanne Guilbert',
                'email_adh' => 'tmartel@wanadoo.fr',
                'login_adh' => 'lebreton.alexandre',
                'mdp_adh' => '{(3mCWC7[YL]n',
                'mdp_adh2' => '{(3mCWC7[YL]n',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 0,
                'prof_adh' => 'Gérant d\'hôtel',
                'titre_adh' => null,
                'ddn_adh' => '1914-05-16',
                'lieu_naissance' => 'Traore',
                'pseudo_adh' => 'helene59',
                'pays_adh' => null,
                'tel_adh' => '0383453389',
                'url_adh' => 'http://www.lesage.com/et-aperiam-labore-est-libero-voluptatem.html',
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-02-03',
                'pref_lang' => 'oc',
                'fingerprint' => 'FAKER_7',
            ),
            'FAKER_8' =>  array (
                'nom_adh' => 'Peltier',
                'prenom_adh' => 'Inès',
                'ville_adh' => 'Thierry-sur-Carre',
                'cp_adh' => '80690',
                'adresse_adh' => '43, impasse Texier',
                'email_adh' => 'qdubois@mendes.fr',
                'login_adh' => 'julie.carlier',
                'mdp_adh' => '.ATai-E6%LIxE{',
                'mdp_adh2' => '.ATai-E6%LIxE{',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Gynécologue',
                'titre_adh' => null,
                'ddn_adh' => '1988-05-29',
                'lieu_naissance' => 'Dijoux-sur-Michaud',
                'pseudo_adh' => 'wpierre',
                'pays_adh' => null,
                'tel_adh' => '01 32 14 47 74',
                'url_adh' => null,
                'activite_adh' => true,
                'id_statut' => 9,
                'date_crea_adh' => '2020-03-28',
                'pref_lang' => 'ar',
                'fingerprint' => 'FAKER_8',
            ),
            'FAKER_9' => array (
                'nom_adh' => 'Marchand',
                'prenom_adh' => 'Audrey',
                'ville_adh' => 'Lenoirdan',
                'cp_adh' => '06494',
                'adresse_adh' => '438, place de Carre',
                'email_adh' => 'luc42@yahoo.fr',
                'login_adh' => 'margot.bousquet',
                'mdp_adh' => 'FH,q5udclwM(',
                'mdp_adh2' => 'FH,q5udclwM(',
                'bool_admin_adh' => false,
                'bool_exempt_adh' => false,
                'bool_display_info' => true,
                'sexe_adh' => 1,
                'prof_adh' => 'Convoyeur garde',
                'titre_adh' => null,
                'ddn_adh' => '1977-09-02',
                'lieu_naissance' => 'Arnaud-sur-Antoine',
                'pseudo_adh' => 'gerard66',
                'pays_adh' => null,
                'tel_adh' => '+33 1 46 04 81 87',
                'url_adh' => 'http://www.thierry.com/',
                'activite_adh' => true,
                'id_statut' => 5,
                'date_crea_adh' => '2019-05-16',
                'pref_lang' => 'fr_FR',
                'fingerprint' => 'FAKER_9',
            )
        );
    }

    /**
     * Get second set of member data but two lines without name.
     *
     * @return array
     */
    private function getMemberData2NoName()
    {
        $data = $this->getMemberData2();
        $data['FAKER_2']['nom_adh'] = '';
        $data['FAKER_5']['nom_adh'] = '';
        return $data;
    }
}
