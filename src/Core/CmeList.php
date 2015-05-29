<?php
/**
 * @author  oke.ugwu
 */

namespace CmeKernel\Core;

use CmeData\CampaignData;
use CmeData\ListData;
use CmeData\ListImportQueueData;
use CmeData\SubscriberData;
use CmeData\UnsubscribeData;
use CmeKernel\Helpers\ListHelper;

class CmeList
{
  private $_tableName = "lists";

  public function exists($id)
  {
    $result = CmeDatabase::conn()->select(
      "SELECT id FROM " . $this->_tableName . " WHERE id = " . $id
    );
    return ($result) ? true : false;
  }

  /**
   * @param $id
   *
   * @return bool| ListData
   */
  public function get($id)
  {
    $list = CmeDatabase::conn()
      ->table($this->_tableName)
      ->where(['id' => $id])
      ->get();

    $data = false;
    if($list)
    {
      $data      = ListData::hydrate(head($list));
      $tableName = ListHelper::getTable($data->id);
      $size      = 0;
      //check if list table exists/
      if(CmeDatabase::schema()->hasTable($tableName))
      {
        $size = CmeDatabase::conn()->table($tableName)->count();
      }
      $data->setSize($size);
    }
    return $data;
  }

  /**
   * @param bool $includeDeleted
   *
   * @return ListData[];
   */
  public function all($includeDeleted = false)
  {
    $return = [];
    if($includeDeleted)
    {
      $result = CmeDatabase::conn()->table($this->_tableName)->get();
    }
    else
    {
      $result = CmeDatabase::conn()->table($this->_tableName)->whereNull(
        'deleted_at'
      )->get();
    }

    foreach($result as $row)
    {
      $list      = ListData::hydrate($row);
      $size      = 0;
      $tableName = ListHelper::getTable($row['id']);
      if(CmeDatabase::schema()->hasTable($tableName))
      {
        $size = CmeDatabase::conn()
          ->table($tableName)
          ->count();
      }
      $list->setSize($size);
      $return[] = $list;
    }

    return $return;
  }

  /**
   * @return ListData;
   * @throws \Exception
   */
  public function any()
  {
    $row = head(
      CmeDatabase::conn()->select(
        "SELECT * FROM " . $this->_tableName . " LIMIT 1"
      )
    );

    return ListData::hydrate($row);
  }

  /**
   * @param ListData $data
   *
   * @return bool|int $id
   */
  public function create(ListData $data)
  {
    if((int)$data->refreshInterval == 0)
    {
      $data->refreshInterval = null;
    }

    $id = CmeDatabase::conn()
      ->table($this->_tableName)
      ->insertGetId(
        $data->toArray()
      );

    //create default schema
    ListHelper::createListTable($id, ['email']);

    return $id;
  }

  /**
   * @param ListData $data
   *
   * @return bool
   */
  public function update(ListData $data)
  {
    CmeDatabase::conn()->table($this->_tableName)
      ->where('id', '=', $data->id)
      ->update($data->toArray());

    return true;
  }

  /**
   * @param int $id
   *
   * @return bool
   */
  public function delete($id)
  {
    $data            = new ListData();
    $data->deletedAt = time();
    CmeDatabase::conn()->table($this->_tableName)
      ->where('id', '=', $id)
      ->update($data->toArray());

    return true;
  }

  /**
   * @param int $listId
   * @param int $offset
   * @param int $limit
   *
   * @return SubscriberData[]
   */
  public function getSubscribers($listId, $offset = 0, $limit = 1000)
  {
    $tableName = ListHelper::getTable($listId);
    //check if list table exists/
    $subscribers = [];
    if(CmeDatabase::schema()->hasTable($tableName))
    {
      //if it does, fetch all subscribers and display
      if(CmeDatabase::conn()->table($tableName)->count())
      {
        $result = CmeDatabase::conn()
          ->table($tableName)
          ->skip($offset)
          ->take($limit)
          ->get();

        foreach($result as $row)
        {
          $subscribers[] = SubscriberData::hydrate($row, false);
        }
      }
    }

    return $subscribers;
  }

  /**
   * @param $subscriberId
   * @param $listId
   *
   * @return bool|SubscriberData
   */
  public function getSubscriber($subscriberId, $listId)
  {
    $data = false;
    if($listId && $subscriberId)
    {
      $table  = ListHelper::getTable($listId);
      $result = CmeDatabase::conn()
        ->table($table)
        ->where(['id' => $subscriberId])
        ->get();

      if($result)
      {
        /**
         * @var SubscriberData $data
         */
        $data = SubscriberData::hydrate(head($result), false);
      }
    }

    return $data;
  }

  /**
   * @param SubscriberData $data
   * @param int            $listId
   *
   * @return bool
   */
  public function addSubscriber(SubscriberData $data, $listId)
  {
    unset($data->id);
    $data->dateCreated = date('Y-m-d H:i:s');
    $added             = false;
    if($listId)
    {
      $table     = ListHelper::getTable($listId);
      $dataArray = $data->toArray();

      $columns = array_keys($dataArray);
      //diff it, so we don't end up with duplicate column names
      $columns = array_diff($columns, ListHelper::inBuiltFields());
      ListHelper::createListTable($listId, $columns);

      CmeDatabase::conn()->table($table)->insert($dataArray);
      $added = false;
    }

    return $added;
  }

  /**
   * @param int $subscriberId
   * @param int $listId
   *
   * @return mixed
   */
  public function deleteSubscriber($subscriberId, $listId)
  {
    $deleted = false;
    if($listId && $subscriberId)
    {
      $table = ListHelper::getTable($listId);
      CmeDatabase::conn()
        ->table($table)
        ->where(['id' => $subscriberId])
        ->delete();
      $deleted = true;
    }

    return $deleted;
  }

  /**
   * @param $email
   *
   * @return bool
   * @throws \Exception
   */
  public function isUnsubscribed($email)
  {
    $result = CmeDatabase::conn()->table('unsubscribes')
      ->where('email', '=', $email)->get(['email']);
    return ($result) ? true : false;
  }

  /**
   * @param UnsubscribeData $data
   *
   * @return bool
   * @throws \Exception
   */
  public function unsubscribe(UnsubscribeData $data)
  {
    return CmeDatabase::conn()->table('unsubscribes')->insert($data->toArray());
  }

  public function getColumns($listId)
  {
    return CmeDatabase::schema()->getColumnListing(
      ListHelper::getTable($listId)
    );
  }

  public function import(ListImportQueueData $data)
  {
    unset($data->id);
    CmeDatabase::conn()->table('import_queue')->insert(
      $data->toArray()
    );

    return true;
  }


  /**
   * @param int $listId - List ID
   *
   * @return CampaignData[]
   */
  public function campaigns($listId)
  {
    $campaigns = CmeDatabase::conn()->table('campaigns')
      ->where(['list_id' => $listId])->get();

    $return = [];
    foreach($campaigns as $campaign)
    {
      $campaign               = CampaignData::hydrate($campaign);
      $campaign->list         = CmeKernel::EmailList()->get($campaign->listId);
      $campaign->brand        = CmeKernel::Brand()->get($campaign->brandId);
      $campaign->smtpProvider = CmeKernel::SmtpProvider()->get(
        $campaign->smtpProviderId
      );
      $return[]               = $campaign;
    }

    return $return;
  }
}
