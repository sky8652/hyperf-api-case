<?php
declare (strict_types=1);

namespace App\RedisModel\System;

use Hyperf\DbConnection\Db;
use Hyperf\Support\Common\RedisModel;

class AclRedis extends RedisModel
{
    protected $key = 'system:acl';
    private $data = [];

    /**
     * Clear Cache
     */
    public function clear(): void
    {
        $this->redis->del($this->key);
    }

    /**
     * Get Cache
     * @param string $key
     * @param int $policy
     * @return array
     */
    public function get(string $key, int $policy): array
    {
        if (!$this->redis->exists($this->key)) {
            $this->update($key);
        } else {
            $raws = $this->redis->hGet($this->key, $key);
            $this->data = !empty($raws) ? json_decode($raws, true) : [];
        }
        switch ($policy) {
            case 0:
                return explode(',', $this->data['read']);
            case 1:
                return array_merge(
                    explode(',', $this->data['read']),
                    explode(',', $this->data['write'])
                );
            default:
                return [];
        }
    }

    /**
     * Refresh Cache
     * @param string $key
     */
    private function update(string $key): void
    {
        $queryLists = Db::table('acl')
            ->where('status', '=', 1)
            ->get(['key', 'write', 'read']);

        if ($queryLists->isEmpty()) {
            return;
        }

        $lists = [];
        foreach ($queryLists->toArray() as $value) {
            $data[$value->key] = json_encode([
                'write' => $value->write,
                'read' => $value->read
            ]);
            if ($key == $value->key) {
                $this->data = [
                    'write' => $value->write,
                    'read' => $value->read
                ];
            }
        }
        $this->redis->hMSet($this->key, $lists);
    }
}