<?php

namespace Ornicar\MessageBundle\Document;

use Ornicar\MessageBundle\Model\Thread as AbstractThread;
use Ornicar\MessageBundle\Model\MessageInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use FOS\UserBundle\Model\UserInterface;

abstract class Thread extends AbstractThread
{
    /**
     * Messages contained in this thread
     *
     * @var Collection of MessageInterface
     */
    protected $messages;

    /**
     * Users participating in this conversation
     *
     * @var Collection of UserInterface
     */
    protected $participants;

    /**
     * Tells, for each participant, if the message is deleted
     *
     * @var array of boolean indexed by user id
     */
    protected $isDeletedByParticipant = array();

    /**
     * Date the last messages were created at.
     * To each user id is associated the date
     * of the last message he did not write.
     *
     * This allows fast sorting of threads in inbox
     *
     * @var array of int timestamps indexed by user id
     */
    protected $datesOfLastMessageWrittenByOtherParticipant = array();

    /**
     * Date the last messages were created at.
     * To each user id is associated the date
     * of the last message he wrote.
     *
     * This allows fast sorting of threads in sentbox
     *
     * @var array of int timestamps indexed by user id
     */
    protected $datesOfLastMessageWrittenByParticipant = array();

    /**
     * Initializes the collections
     */
    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->participants = new ArrayCollection();
    }

    /**
     * Gets the messages contained in the thread
     *
     * @return array of MessageInterface
     */
    public function getMessages()
    {
        return $this->messages->toArray();
    }

    /**
     * Adds a new message to the thread
     *
     * @param MessageInterface $message
     */
    public function addMessage(MessageInterface $message)
    {
        $this->messages->add($message);
        $this->denormalize($message);
    }

    /**
     * Gets the users participating in this conversation
     *
     * @return array of UserInterface
     */
    public function getParticipants()
    {
        return $this->participants->toArray();
    }

    /**
     * Adds a participant to the thread
     * If it already exists, nothing is done.
     *
     * @param UserInterface $participant
     * @return null
     */
    public function addParticipant(UserInterface $participant)
    {
        if (!$this->isParticipant($participant)) {
            $this->participants->add($participant);
        }
    }

    /**
     * Tells if the user participates to the conversation
     *
     * @param UserInterface $user
     * @return boolean
     */
    public function isParticipant(UserInterface $user)
    {
        return $this->participants->contains($user);
    }

    /**
     * Tells if this thread is deleted by this participant
     *
     * @return bool
     */
    public function isDeletedByParticipant(UserInterface $participant)
    {
        return $this->isDeletedByParticipant[$participant->getId()];
    }

    /**
     * Sets whether or not this participant has deleted this thread
     *
     * @param UserInterface $participant
     * @param boolean $isDeleted
     */
    public function setIsDeletedByParticipant(UserInterface $participant, $isDeleted)
    {
        $this->isDeletedByParticipant[$participant->getId()] = (boolean) $isDeleted;
    }

    /**
     * Performs denormalization tricks
     * based on a message belonging to this thread.
     * Updates participants and last message dates.
     *
     * Take it easy, this code is tested in Tests\Document\ThreadTest ;)
     *
     * @param MessageInterface $message
     */
    protected function denormalize(MessageInterface $message)
    {
        $sender = $message->getSender();
        $this->addParticipant($sender);
        $message->setIsReadByParticipant($sender, true);

        // Update the last message dates if needed
        $messageTs = $message->getCreatedAt()->getTimestamp();
        $senderId = $sender->getId();
        foreach ($this->participants as $participant) {
            $participantId = $participant->getId();
            if ($participantId != $senderId) {
                if (!isset($this->datesOfLastMessageWrittenByOtherParticipant[$participantId]) || $this->datesOfLastMessageWrittenByOtherParticipant[$participantId] < $messageTs) {
                    $this->datesOfLastMessageWrittenByOtherParticipant[$participantId] = $messageTs;
                }
                $message->setIsReadByParticipant($participant, false);
            } elseif (!isset($this->datesOfLastMessageWrittenByParticipant[$participantId]) || $this->datesOfLastMessageWrittenByParticipant[$participantId] < $messageTs) {
                $this->datesOfLastMessageWrittenByParticipant[$participantId] = $messageTs;
            }
            if (!array_key_exists($participantId, $this->isDeletedByParticipant)) {
                $this->isDeletedByParticipant[$participantId] = false;
            }
        }
        // having theses sorted by user does not harm, and it makes unit testing easier
        ksort($this->datesOfLastMessageWrittenByParticipant);
        ksort($this->datesOfLastMessageWrittenByOtherParticipant);
    }
}
