<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Socket;

use Hyperf\Protocol\ProtocolPackerInterface;
use Hyperf\Socket\Exception\SocketException;
use Swoole\Coroutine\Socket as CoSocket;

class Socket implements SocketInterface
{
    /**
     * @var CoSocket
     */
    protected $socket;

    /**
     * @var ProtocolPackerInterface
     */
    protected $packer;

    /**
     * SOCK_STREAM | SOCK_DGRAM.
     * @var int
     */
    protected $pipeType;

    public function __construct(CoSocket $socket, ProtocolPackerInterface $packer, int $pipeType = SOCK_STREAM)
    {
        $this->socket = $socket;
        $this->packer = $packer;
        $this->pipeType = $pipeType;
    }

    public function send($data, float $timeout = -1)
    {
        $string = $this->packer->pack($data);

        $len = $this->socket->sendAll($string, $timeout);

        if ($len !== strlen($string)) {
            throw new SocketException('Send failed: ' . $this->socket->errMsg);
        }

        return $len;
    }

    public function recv(float $timeout = -1)
    {
        if ($this->pipeType === SOCK_DGRAM) {
            return $this->recvDgram($timeout);
        }

        return $this->recvStream($timeout);
    }

    protected function recvStream(float $timeout)
    {
        $head = $this->socket->recvAll($this->packer::HEAD_LENGTH, $timeout);
        if ($head === false) {
            return false;
        }

        if (strlen($head) !== $this->packer::HEAD_LENGTH) {
            throw new SocketException('Recv head failed: ' . $this->socket->errMsg);
        }

        $len = $this->packer->length($head);

        if ($len === 0) {
            throw new SocketException('Recv body failed: body length is zero.');
        }

        $body = $this->socket->recvAll($len, $timeout);
        if (strlen($body) !== $len) {
            throw new SocketException('Recv body failed: ' . $this->socket->errMsg);
        }

        return $this->packer->unpack($head . $body);
    }

    protected function recvDgram(float $timeout)
    {
        $body = $this->socket->recv(65536, $timeout);
        if ($body === false) {
            return false;
        }

        if (strlen($body) === 0) {
            throw new SocketException('Recv body failed: body length is zero.');
        }

        return $this->packer->unpack($body);
    }
}
