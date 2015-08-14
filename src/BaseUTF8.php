<?php

namespace mpyw\BaseUTF8;

class BaseUTF8 {

    private $table;
    private $power;

    public function __construct($chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/') {
        if (is_string($chars)) {
            $chars = preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!is_array($chars)) {
            throw new \InvalidArgumentException('$chars must be array or string.');
        }
        $uniq = $values = [];
        foreach ($chars as $char) {
            if (!is_string($char)) {
                throw new \InvalidArgumentException('Elements of $chars must be string.');
            }
            if (isset($uniq[$char])) {
                throw new \InvalidArgumentException('$chars contains duplicated characters.');
            }
            $uniq[$char] = true;
            $values[] = $char;
        }
        $log = log(count($values), 2);
        if ((string)$log !== (string)(int)$log) {
            throw new \InvalidArgumentException('log_2(count($chars)) must be absolutely integer.');
        }
        $this->power = (int)$log;
        $this->table['encode'] = $values;
        $this->table['decode'] = array_flip($values);
    }

    public function encode($data) {
        $length = strlen($data);
        $buffer = '';
        $i = $width = $code = 0;
        while (true) {
            if ($width >= $this->power) {
                $width -= $this->power;
                $buffer .= $this->table['encode'][$code >> $width];
                $code &= (1 << $width) - 1;
            } elseif ($i >= $length) {
                break;
            } else {
                $code <<= 8;
                $code |= ord($data[$i++]);
                $width += 8;
            }
        }
        if ($width) {
            $buffer .= $this->table['encode'][$code << ($this->power - $width)];
        }
        return $buffer;
    }

    public function decode($data) {
        $length = strlen($data);
        $buffer = $bytes = '';
        $i = $width = $code = 0;
        $follows = -1;
        while (true) {
            if ($follows === 0) {
                if (!isset($this->table['decode'][$bytes])) {
                    throw new \RuntimeException('Broken data.');
                }
                $code <<= $this->power;
                $code |= $this->table['decode'][$bytes];
                $width += $this->power;
                $bytes = '';
                $follows = -1;
            } elseif ($width >= 8) {
                $width -= 8;
                $buffer .= chr($code >> $width);
                $code &= (1 << $width) - 1;
            } elseif ($i >= $length) {
                break;
            } else {
                $char = $data[$i++];
                $ord = ord($char);
                if ($bytes === '') {
                    $follows = -1;
                    switch (true) {
                        case (++$follows || true) && $ord >= 0x00 && $ord <= 0x7F:
                        case (++$follows || true) && $ord >= 0xC0 && $ord <= 0xDF:
                        case (++$follows || true) && $ord >= 0xE0 && $ord <= 0xEF:
                        case (++$follows || true) && $ord >= 0xF0 && $ord <= 0xF7:
                            $bytes .= $char;
                            break;
                        default:
                            throw new \RuntimeException('Invalid UTF-8 sequence.');
                    }
                } elseif ($ord < 0x80 || $ord > 0xBF) {
                    throw new \RuntimeException('Invalid UTF-8 sequence.');
                } else {
                    --$follows;
                    $bytes .= $char;
                }
            }
        }
        return $buffer;
    }

}
