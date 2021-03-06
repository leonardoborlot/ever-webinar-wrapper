<?php 
namespace App\Models;

class Schedule implements \JsonSerializable
{
    private $id;
    private $originalDateTimeStr;
    private $timeZoneStr;
    private $timestamp;
    private $repetition;
    private $repetitionDay;

    public function __construct($originalDateTimeStr, $id=null)
    {
        $this->id                  = $id;
        $this->originalDateTimeStr = $originalDateTimeStr;
    }


    public function setId($id)
    {
        $this->id = $id;
    }


    public function processTimezoneStr()
    {
        $this->repetition = $this->processRepetition();
        $this->timestamp = $this->processTimestamp();
    }

    public function getRepetition()
    {
        return $this->repetition;
    }


    public function processRepetition()
    {
        $str = $this->originalDateTimeStr;
        if( strpos($str, "Every" ) === false ) return "";
        if( strpos($str, "Every Day" ) !== false ) return "Every Day";

        $str = trim($str, " ");
        $matches = array();
        if(preg_match("/^Every (\w+)/", $str, $matches)){
            return $matches[0];
        }
        return "";
    }

    public function getTimestamp()
    {
        return $this->timestamp;
    }


    public function createDateTimeFromTimezone()
    {
        $dateTime = new \DateTime("now", new \DateTimeZone($this->timeZoneStr));
        return $dateTime->setTimestamp($this->timestamp);
    }

    public function getISOUTCDateTime($timezone="UTC")
    {
        $dateTime = new \DateTime("now", new \DateTimeZone("utc"));
        $dateTime->setTimestamp($this->timestamp);
        return $dateTime->format(\DateTime::ATOM);
    }

    public function processTimestamp()
    {
        $repetition = $this->repetition;
        $str = $this->originalDateTimeStr;
        $day = "";
        $timestamp = null;
        $dateTimeObj = null;
        if ($repetition) {
            $repetitionArr = explode(" ", $repetition);
            if ($repetitionArr[1] != "Day")
                $day = "{$repetitionArr[1]}, " ;

            $matches = array();
            if(preg_match("/\d\d:\d\d \w\w/", $str, $matches) && (strtotime($day . $matches[0]) !== false)){
                    $timestamp = (new \DateTime($day . $matches[0],
                        new \DateTimeZone($this->timeZoneStr)))
                        ->format("U");
            }
        }else if(strtotime($str) !== false) {
                $dateTimeObj = new \DateTime(
                    $str, new \DateTimeZone($this->timeZoneStr));
                $timestamp = $dateTimeObj->format("U");
        }

        if (! $timestamp) {
            // ex :"Thursday, 6 Jul 08:30 AM"
            $dateTimeObj = \DateTime::createFromFormat ( "l, j M h:i A",
                $str,
                new \DateTimeZone($this->timeZoneStr)
            );

            $timestamp = ($dateTimeObj !== false) ? null : $dateTimeObj->getTimestamp();
        }

        return $timestamp;
    }

    public function setTimeZoneStr($timeZoneStr)
    {
        // handle the auto time zone .. auto is convert the time zone to EST
        // https://www.timeanddate.com/time/zones/est
        if (strtolower($timeZoneStr) == "auto") {
            $timeZoneStr = "America/Jamaica";
        }
        $this->timeZoneStr = $timeZoneStr;
        $this->processTimezoneStr();
    }

    public function jsonSerialize() {
        return array(
            "schedule_id" => $this->id,
            "repetition" => $this->getRepetition(),
            "isoUTC" => $this->getISOUTCDateTime(),
            "originalTimezone" => $this->timeZoneStr,
            "originalTime" => $this->originalDateTimeStr
            );
    }

    public static function buildFromStdClass($stdClass, $timeZoneStr=null)
    {
        $date = is_string($stdClass) ? $stdClass : $stdClass->date;
        $instance = new self($date);
        if (isset($stdClass->schedule))
            $instance->setId($stdClass->schedule);

        $instance->setTimeZoneStr($timeZoneStr);

        return $instance;
    }
}
