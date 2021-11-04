<?php

namespace SwooleTW\Http\Websocket;

use http\Client\Curl\User;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use Swoole\Table;

/**
 * Trait Authenticatable
 *
 * @property-read \SwooleTW\Http\Websocket\Rooms\RoomsContract $room
 */
trait Authenticatable
{
    protected $table_names = [];

    protected $userId;

    protected array $fds = [];

    /**
     * Login using current user.
     *
     * @param  AuthenticatableContract  $user
     *
     * @return mixed
     */
    public function loginUsing(AuthenticatableContract $user)
    {
        return $this->loginUsingId($user->getAuthIdentifier());
    }

    /**
     * Sets connection current user.
     *
     * @param  AuthenticatableContract  $user
     *
     * @return mixed
     */
    public function setUser(AuthenticatableContract $user)
    {
        /** @var Table $table */
        $table = App::make('swoole.table')->get('online_users');
        $table->set($this->sender, ['id' => $user->getAuthIdentifier(), 'fd' => $this->sender]);
        return true;
    }

    /**
     * Login using current userId.
     *
     * @param $userId
     *
     * @return mixed
     */
    public function loginUsingId($userId)
    {
        return $this->join(static::USER_PREFIX.$userId);
    }

    /**
     * Logout with current sender's fd.
     *
     * @return mixed
     */
    public function logout()
    {
        if (is_null($userId = $this->getUserId())) {
            return null;
        }

        return $this->leave(static::USER_PREFIX.$userId);
    }

    /**
     * Set multiple recepients' fds by users.
     *
     * @param $users
     *
     * @return \SwooleTW\Http\Websocket\Authenticatable
     */
    public function toUser($users)
    {
        $users = is_object($users) ? func_get_args() : $users;

        $userIds = array_map(function (AuthenticatableContract $user) {
            $this->checkUser($user);

            return $user->getAuthIdentifier();
        }, $users);

        return $this->toUserId($userIds);
    }

    /**
     * Set multiple recepients' fds by userIds.
     *
     * @param $userIds
     *
     * @return \SwooleTW\Http\Websocket\Authenticatable
     */
    public function toUserId($userIds)
    {
        $userIds = is_string($userIds) || is_integer($userIds) ? func_get_args() : $userIds;

        foreach ($userIds as $userId) {
            $fds = $this->room->getClients(static::USER_PREFIX.$userId);
            $this->to($fds);
        }

        return $this;
    }

    /**
     * Get current auth user id by sender's fd.
     */
    public function getUserId()
    {
        if (isset($this->fds[$this->sender])) {
            return $this->fds[$this->sender];
        }
        $table = App::make('swoole.table')->get('online_users');
        return $table->get($this->sender)['id'];
    }

    /**
     * Get current auth user by sender's fd.
     */
    public function getUser(): ?AuthenticatableContract
    {
        $id = $this->getUserId();
        return app(UserProvider::class)->retrieveById($id);
    }

    /**
     * Check if a user is online by given userId.
     *
     * @param $userId
     *
     * @return bool
     */
    public function isUserIdOnline($userId)
    {
        return !empty($this->room->getClients(static::USER_PREFIX.$userId));
    }

    /**
     * Check if user object implements AuthenticatableContract.
     *
     * @param $user
     */
    protected function checkUser($user)
    {
        if (!$user instanceof AuthenticatableContract) {
            throw new InvalidArgumentException('user object must implement '.AuthenticatableContract::class);
        }
    }
}
