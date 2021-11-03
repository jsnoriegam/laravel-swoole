<?php

namespace SwooleTW\Http\Websocket\Rooms;

use Swoole\Table;

class TableRoomImproved implements RoomContract
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var \Swoole\Table
     */
    protected $rooms;

    /**
     * @var \Swoole\Table
     */
    protected $fds;

    /**
     * TableRoom constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Do some init stuffs before workers started.
     *
     * @return \SwooleTW\Http\Websocket\Rooms\RoomContract
     */
    public function prepare(): RoomContract
    {
        $this->initRoomsTable();
        $this->initFdsTable();

        return $this;
    }

    /**
     * Add a socket fd to multiple rooms.
     *
     * @param int fd
     * @param array|string rooms
     */
    public function add(int $fd, $rooms)
    {
        $rooms = is_array($rooms) ? $rooms : [$rooms];

        foreach ($rooms as $room) {
            $fds = $this->getClients($room);
            if (in_array($fd, $fds)) {
                continue;
            }
            $fds[] = $fd;
            $this->setClients($room, $fds);
        }

        $this->setRooms($fd, $rooms);
    }

    /**
     * Delete a socket fd from multiple rooms.
     *
     * @param int fd
     * @param array|string rooms
     */
    public function delete(int $fd, $rooms = [])
    {
        $allRooms = $this->getRooms($fd);
        $rooms = is_array($rooms) ? $rooms : [$rooms];
        $rooms = count($rooms) ? $rooms : $allRooms;

        foreach ($rooms as $room) {
            $fds = $this->getClients($room);
            if (! in_array($fd, $fds)) {
                continue;
            }
            $this->setClients($room, array_values(array_diff($fds, [$fd])));
        }

        $this->setRooms($fd, array_values(array_diff($allRooms, $rooms)));
    }

    /**
     * Get all sockets by a room key.
     *
     * @param string room
     *
     * @return array
     */
    public function getClients(string $room)
    {
        return $this->getValue($room, RoomContract::ROOMS_KEY) ?? [];
    }

    /**
     * Get all rooms by a fd.
     *
     * @param int fd
     *
     * @return array
     */
    public function getRooms(int $fd)
    {
        return $this->getValue($fd, RoomContract::DESCRIPTORS_KEY) ?? [];
    }

    /**
     * @param string $room
     * @param array $fds
     *
     * @return \SwooleTW\Http\Websocket\Rooms\TableRoom
     */
    protected function setClients(string $room, array $fds): TableRoomImproved
    {
        return $this->setValue($room, $fds, RoomContract::ROOMS_KEY);
    }

    /**
     * @param int $fd
     * @param array $rooms
     *
     * @return \SwooleTW\Http\Websocket\Rooms\TableRoom
     */
    protected function setRooms(int $fd, array $rooms): TableRoomImproved
    {
        return $this->setValue($fd, $rooms, RoomContract::DESCRIPTORS_KEY);
    }

    /**
     * Init rooms table
     */
    protected function initRoomsTable(): void
    {
        $this->rooms = new Table($this->config['room_rows']);
        $this->rooms->column('value', Table::TYPE_STRING, $this->config['room_size']);
        $this->rooms->create();
    }

    /**
     * Init descriptors table
     */
    protected function initFdsTable()
    {
        $this->fds = new Table($this->config['client_rows']);
        $this->fds->column('value', Table::TYPE_STRING, $this->config['client_size']);
        $this->fds->create();
    }

    /**
     * Set value to table
     *
     * @param $key
     * @param array $value
     * @param string $table
     *
     * @return $this
     */
    public function setValue($key, array $value, string $table)
    {
        $this->checkTable($table);

        $this->$table->set($key, ['value' => json_encode($value)]);

        return $this;
    }

    /**
     * Get value from table
     *
     * @param string $key
     * @param string $table
     *
     * @return array|mixed
     */
    public function getValue(string $key, string $table)
    {
        $this->checkTable($table);

        $value = $this->$table->get($key);

        return $value ? json_decode($value['value'], true) : [];
    }

    /**
     * Check table for exists
     *
     * @param string $table
     */
    protected function checkTable(string $table)
    {
        if (! property_exists($this, $table) || ! $this->$table instanceof Table) {
            throw new \InvalidArgumentException("Invalid table name: `{$table}`.");
        }
    }
}
