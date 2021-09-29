<?php

namespace SwooleTW\Http\Websocket\Rooms;

use ArrayObject;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Swoole\Table;
use SwooleTW\Http\Table\SwooleTable;

class Room implements Arrayable, JsonSerializable
{
    protected ArrayObject $params;

    private Table $rooms, $onlineUsers, $roomFds;

    public function __construct(public int $id, public ?int $limit)
    {
        $this->rooms = \App::make('swoole.table')->get('rooms');
        $this->onlineUsers = \App::make('swoole.table')->get('online_users');
        $this->roomFds = \App::make('swoole.table')->get('room_fds');
    }

    public function get($filter): array
    {
        $return = [];
        if (!is_array($filter)) {
            $filter = [$filter];
        }
        foreach ($filter as $key) {
            $return = $filter[$key];
        }
        return $return;
    }

    public function set(array $options, bool $notify = false): void
    {
        $updated = [];
        foreach ($options as $key => $value) {
            $keys = explode('.', $key);
            $firstKey = $keys[0];
            $val = &$this[$firstKey];
            $updatedVal = &$updated[$firstKey];
            unset($keys[0]);
            foreach ($keys as $key) {
                $val = &$val[$key];
                $updatedVal = &$updatedVal[$key];
            }
            $val = $value;
            $updatedVal = $value;
        }
        if ($notify && count($updated)) {
            $this->broadcast('update', $updated);
        }
    }

    public function join(int $user, ?int $fd): bool
    {
        if ($this->params['status'] == 'waiting') {
            $userIndex = count($this->users);
            $status = $userIndex >= $this->limit - 1 ? 'waiting' : 'active';
            $this->set(['params.status' => $status, "users.$userIndex" => $user]);
            if ($fd) {
                $this->subscribe($user, $fd);
            }
            return true;
        }
        return false;
    }

    public function _subscribe(int $user, $fd): bool
    {
        $isJoined = in_array($user, (array)$this->users);
        if (!$this->id || !$isJoined) {
            return false;
        }
        $isSubscribed = in_array($fd, (array)$this->fds);
        if ($isSubscribed) {
            return true;
        }
        $fds = [];
        if ($this->onlineUsers->exists($user)) {
            $onlineUser = $this->onlineUsers->get($user);
            if ($onlineUser['room'] > 0 && $onlineUser['room'] != $this->id) {
                return false;
            }
            $fds = json_decode($onlineUser['fds'], true);
        }
        $this->_fds[] = $fds;
        $this->roomFds->set($fd, ['room' => $this->id, 'user' => $user]);
        $fds[] = $fd;
        $this->onlineUsers->set($user, ['room' => $isJoined ? $this->id : 0, 'fds' => json_encode($fds)]);
        return true;
    }

    public function subscribe(int $user, int $fd): bool
    {
        return $this->_subscribe($user, $fd);
    }

    public function broadcast(string $event, $data = []): void
    {
        if ($this->fds) {
            app(Websocket::class)->to($this->fds)->emit($event, $data);
        }
    }

    public function getAll(): Table
    {
        return $this->rooms;
    }


    public function toArray()
    {

    }

    public function jsonSerialize()
    {

    }
}