<?php
/**
 * Import Definitions.
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2016 W-Vision (http://www.w-vision.ch)
 * @license    https://github.com/w-vision/ImportDefinitions/blob/master/gpl-3.0.txt GNU General Public License version 3 (GPLv3)
 */

namespace ImportDefinitions\Model\Definition;

use ImportDefinitions\Model\AbstractProvider;
use ImportDefinitions\Model\Mapping;
use Pimcore\Model;
use Pimcore\Tool;

class Dao extends Model\Dao\PhpArrayTable
{
    /**
     * Configure Configuration File.
     */
    public function configure()
    {
        parent::configure();
        $this->setFile('importdefinitions');
    }

    /**
     * Get Configuration By Id.
     *
     * @param null $id
     *
     * @throws \Exception
     */
    public function getById($id = null)
    {
        if ($id != null) {
            $this->model->setId($id);
        }

        $data = $this->db->getById($this->model->getId());

        if (isset($data['id'])) {
            $this->assignVariablesToModel($data);
        } else {
            throw new \Exception('Definition with id: '.$this->model->getId().' does not exist');
        }
    }

    /**
     * @param array $data
     * @return void
     */
    protected function assignVariablesToModel($data)
    {
        parent::assignVariablesToModel($data);

        foreach ($data as $key => $value) {
            if ($key === 'providerConfiguration') {
                //get provider class
                $class = 'ImportDefinitions\\Model\\Provider\\' . ucfirst($this->model->getProvider());

                if (Tool::classExists($class)) {
                    $provider = new $class();

                    if ($provider instanceof AbstractProvider) {
                        $provider->setValues($value);

                        $this->model->setProviderConfiguration($provider);
                    }
                }
            } elseif ($key === 'mapping') {
                $maps = array();

                foreach ($this->model->getMapping() as $map) {
                    if (is_array($map)) {
                        $mapObj = new Mapping();
                        $mapObj->setValues($map);

                        $maps[] = $mapObj;
                    }
                }

                $this->model->setMapping($maps);
            }
        }
    }

    /**
     * Get Definition by name.
     *
     * @param null $name
     *
     * @throws \Exception
     */
    public function getByName($name = null)
    {
        if ($name != null) {
            $this->model->setName($name);
        }

        $name = $this->model->getName();

        $data = $this->db->fetchAll(function ($row) use ($name) {
            if ($row['name'] == $name) {
                return true;
            }

            return false;
        });

        if (count($data) && $data[0]['id']) {
            $this->assignVariablesToModel($data[0]);
        } else {
            throw new \Exception('Definition with name: '.$this->model->getName().' does not exist');
        }
    }

    /**
     * save configuration.
     *
     * @throws \Exception
     */
    public function save()
    {
        $ts = time();
        if (!$this->model->getCreationDate()) {
            $this->model->setCreationDate($ts);
        }
        $this->model->setModificationDate($ts);

        try {
            $dataRaw = get_object_vars($this->model);
            $data = [];
            $allowedProperties = ['id', 'name', 'provider', 'class', 'providerConfiguration', 'creationDate',
                'modificationDate', 'mapping', 'objectPath', 'cleaner', 'key', 'renameExistingObjects',
                'relocateExistingObjects', 'filter', 'runner', 'createVersion', 'stopOnException',
                'failureNotificationDocument', 'successNotificationDocument'];

            foreach ($dataRaw as $key => $value) {
                if (in_array($key, $allowedProperties)) {
                    if ($key === 'providerConfiguration') {
                        if ($value) {
                            $data[$key] = get_object_vars($value);
                        }
                    } elseif ($key === 'mapping') {
                        if ($value) {
                            $data[$key] = array();

                            if (is_array($value)) {
                                foreach ($value as $map) {
                                    $data[$key][] = get_object_vars($map);
                                }
                            }
                        }
                    } else {
                        $data[$key] = $value;
                    }
                }
            }
            $this->db->insertOrUpdate($data, $this->model->getId());
        } catch (\Exception $e) {
            throw $e;
        }

        if (!$this->model->getId()) {
            $this->model->setId($this->db->getLastInsertId());
        }
    }

    /**
     * Deletes object from database.
     */
    public function delete()
    {
        $this->db->delete($this->model->getId());
    }
}
