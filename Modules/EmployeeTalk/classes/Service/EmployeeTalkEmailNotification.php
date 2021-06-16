<?php
declare(strict_types=1);

namespace ILIAS\EmployeeTalk\Service;

final class EmployeeTalkEmailNotification
{
    /**
     * @var string $salutation
     */
    private $salutation;
    /**
     * @var string $dateHeader
     */
    private $dateHeader;
    /**
     * @var string $talkTitle
     */
    private $talkTitle;
    /**
     * @var string $appointmentDetails
     */
    private $appointmentDetails;
    /**
     * @var string[] $dates
     */
    private $dates;

    /**
     * EmployeeTalkEmailNotification constructor.
     * @param string $salutation
     * @param string $dateHeader
     * @param string $talkTitle
     * @param string $appointmentDetails
     * @param string[] $dates
     */
    public function __construct(
        string $salutation,
        string $dateHeader,
        string $talkTitle,
        string $appointmentDetails,
        array $dates
    ) {
        $this->salutation = $salutation;
        $this->dateHeader = $dateHeader;
        $this->talkTitle = $talkTitle;
        $this->appointmentDetails = $appointmentDetails;
        $this->dates = $dates;
    }

    /**
     * @return string
     */
    public function getSalutation() : string
    {
        return $this->salutation;
    }

    /**
     * @return string
     */
    public function getDateHeader() : string
    {
        return $this->dateHeader;
    }

    /**
     * @return string
     */
    public function getTalkTitle() : string
    {
        return $this->talkTitle;
    }

    /**
     * @return string
     */
    public function getAppointmentDetails() : string
    {
        return $this->appointmentDetails;
    }

    /**
     * @return string[]
     */
    public function getDates() : array
    {
        return $this->dates;
    }

    public function __toString(): string
    {
        $dateList = "";
        foreach ($this->dates as $date) {
            $dateList .= "- $date\r\n";
        }

        return $this->getSalutation() . "\r\n\r\n"
            . $this->getAppointmentDetails() . "\r\n"
            . $this->getTalkTitle() . "\r\n\r\n"
            . $this->getDateHeader() . ":\r\n"
            . $dateList;
    }

}