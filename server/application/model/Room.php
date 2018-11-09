<?php
/*
 * This file is part of the NB Framework package.
 *
 * Copyright (c) 2018 https://nb.cx All rights reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace model;

use nb\Model;
use nb\Pool;
use util\Auth;
use util\Redis;

/**
 * 游戏房间
 *
 * @package model
 * @link https://nb.cx
 * @author: collin <collin@nb.cx>
 * @date: 2018/10/31
 */
class Room extends Model {

    /**
     * Iterator
     */
    public function current() {
        $this->row = current($this->stack);

        if(is_array($this->row)) {
            return $this;
        }
        if($this->row) {
            $row = Redis::hGetAll('room:'.$this->row);
            if($row)  {
                $this->row = $row;
            }
            else {
                $this->row = self::create($this->row);
            }
            return $this;

        }
        return false;
    }

    //创建房间
    private static function create($id) {
        $room = [
            'id'=>$id,
            //等待wait,//大牌阶段startd，//end结束清算阶段
            'status'=>'wait',
            //starting

            //牌桌座位
            'a'=>0, //座位A
            'b'=>0, //座位B
            'c'=>0, //座位C

            //地主
            //'landseat'=>0,
            //最后出牌的位置
            //'laster'=>0,
        ];
        Redis::hMset('room:'.$id,$room);
        return $room;
    }

    public static function init() {
        return Pool::value(get_called_class(),function () {
            return new self([1,2,3,4,5]);
        });
    }

    public static function get($id) {
        $room = Redis::hGetAll('room:'.$id);
        if(!$room) {
            $room = self::create($id);
        }
        return new self($room);
        /*
        return Pool::value(get_called_class().':'.$id,function () use ($id) {
            $room = Redis::hGetAll('room:'.$id);
            if(!$room) {
                $room = self::create($id);
            }
            return new self($room);
        });
        */
    }

    protected function _name() {
        return '房间'.$this->id;
    }

    //房间玩家人数
    protected function _number() {
        $i = 0;
        foreach (['a','b','c'] as $v) {
            $this->$v and $i++;
        }
        return $i;
    }

    public function add(User $user) {
        foreach (['a','b','c'] as $v) {
            if($this->$v) {
                continue;
            }
            $seat = [
                'fd'  =>$user->fd,
                'name'=> $user->name,
                'coin'=> $user->coin,
                'ready' => 0
            ];
            $this->$v = $seat;
            //记录玩家的房间和座位
            $user->room = "{$this->id}-{$v}";
            return true;
        }
        return true;
    }

    public function del($seat) {
        Redis::hMset('room:'.$this->id,[$seat=>0]);
        return 0;
    }

    protected function _a() {
        $a = $this->raw('a');
        if($a) {
            return json_decode($a,true);
        }
        return [];
    }

    protected function _b() {
        $b = $this->raw('b');
        if($b) {
            return json_decode($b,true);
        }
        return [];
    }

    protected function _c() {
        $c = $this->raw('c');
        if($c) {
            return json_decode($c,true);
        }
        return [];
    }

    protected function ___a($player) {
        if($player) {
            $player = array_merge($this->a,$player);
            Redis::hMset('room:'.$this->id,['a'=>json_encode($player)]);
            return $player;
        }
        Redis::hMset('room:'.$this->id,['a'=>0]);
        return 0;
    }

    protected function ___b($player) {
        if($player) {
            $player = array_merge($this->b,$player);
            Redis::hMset('room:'.$this->id,['b'=>json_encode($player)]);
            return $player;
        }
        Redis::hMset('room:'.$this->id,['b'=>0]);
        return 0;
    }

    protected function ___c($player) {
        if($player) {
            $player = array_merge($this->c,$player);
            Redis::hMset('room:'.$this->id,['c'=>json_encode($player)]);
            return $player;
        }
        Redis::hMset('room:'.$this->id,['c'=>0]);
        return 0;
    }

    //底牌
    protected function _pocket() {
        return json_decode($this->raw('pocket'),true);
    }

    //出牌
    protected function _lead() {
        return json_decode($this->raw('lead'),true);
    }

    //设置地主
    protected function ___landowner($seat) {
        Redis::hMset('room:'.$this->id,[
            'landowner'=>$seat,
            'leader'=>$seat,
            'lead'=>json_encode([]),
            'win'=>0  //最先出完牌的人
        ]);
        return $seat;
    }

    //
    protected function ___call($call) {
        Redis::hmset('room:'.$this->id,['call'=>json_encode($call)]);
        return $call;
    }

    protected function _call() {
        return json_decode($this->raw('call'),true);
    }

    protected function ___win($seat) {
        Redis::hmset('room:'.$this->id,[
            'win'=>$seat,
            'leader'=>0
        ]);
        $this->tmp['leader'] = 0;
        return $seat;
    }

    protected function ___leader($seat) {
        Redis::hMset('room:'.$this->id,[
            'leader'=>$seat
        ]);
        return $seat;
    }
}