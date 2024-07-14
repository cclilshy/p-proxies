<?php

namespace App\Proxies\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int|null    $id
 * @property string      $protocol
 * @property string      $host
 * @property int         $port
 * @property int|null    $delay
 * @property int|null    $fail
 * @property string|null $create_time
 * @property string|null $update_time
 * @property int|null    $status
 * @property string|null $username
 * @property string|null $password
 * @property int|null    $used
 * @method static Builder|ProxiesModel newModelQuery()
 * @method static Builder|ProxiesModel newQuery()
 * @method static Builder|ProxiesModel query()
 * @method static Builder|ProxiesModel whereCreateTime($value)
 * @method static Builder|ProxiesModel whereDelay($value)
 * @method static Builder|ProxiesModel whereFail($value)
 * @method static Builder|ProxiesModel whereHost($value)
 * @method static Builder|ProxiesModel whereId($value)
 * @method static Builder|ProxiesModel wherePort($value)
 * @method static Builder|ProxiesModel whereProtocol($value)
 * @method static Builder|ProxiesModel whereStatus($value)
 * @method static Builder|ProxiesModel whereUpdateTime($value)
 * @method static Builder|ProxiesModel wherePassword($value)
 * @method static Builder|ProxiesModel whereUsername($value)
 * @method static Builder|ProxiesModel whereUsed($value)
 * @mixin Eloquent
 */
class ProxiesModel extends Model
{
    public const string CREATED_AT = 'create_time';
    public const string UPDATED_AT = 'update_time';

    public    $timestamps = true;
    protected $table      = 'proxy';
    protected $guarded    = [];
    protected $dateFormat = 'U';

    /**
     * @return ProxiesModel|null
     */
    public static function shift(): ProxiesModel|null
    {
        return ProxiesModel::whereStatus(1)->orderBy('used', 'asc')->first()?->used();
    }

    /**
     * @return ProxiesModel
     */
    public function used(): ProxiesModel
    {
        $this->used++;
        $this->timestamps = false;
        $this->save();
        return $this;
    }

    /**
     * @param int $delay
     * @return void
     */
    public function valid(int $delay): void
    {
        if ($this->status !== 1) {
            $this->used = 0;
        }
        $this->fail   = 0;
        $this->status = 1;
        $this->delay  = $delay;
        $this->save();
    }

    /**
     * @return void
     */
    public function invalid(): void
    {
        $this->status = -1;
        $this->used   = 0;
        $this->fail++;
        $this->delay = null;

        if ($this->fail >= 3) {
            $this->delete();
        } else {
            $this->save();
        }
    }
}
