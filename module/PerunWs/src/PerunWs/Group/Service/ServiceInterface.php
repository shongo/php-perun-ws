<?php

namespace PerunWs\Group\Service;


/**
 * Group service interface.
 */
interface ServiceInterface
{


    /**
     * Retrieves a single group by its ID.
     * 
     * @param integer $id
     * @return \InoPerunApi\Entity\Group
     */
    public function fetch($id);


    /**
     * Retrieves all groups
     * 
     * @return \InoPerunApi\Entity\Collection\GroupCollection
     */
    public function fetchAll();


    /**
     * Creates a new group and returns it.
     * 
     * @param array $data
     * @return \InoPerunApi\Entity\Group
     */
    public function create($data);


    /**
     * Modifies partially the group and returns the new version.
     * 
     * @param integer $id
     * @param array $data
     * @return \InoPerunApi\Entity\Group
     */
    public function patch($id, $data);


    /**
     * Deletes the group.
     * 
     * @param integer $id
     * @return boolean
     */
    public function delete($id);


    /**
     * Returns the list of group's members.
     * 
     * @param integer $id
     * @return \InoPerunApi\Entity\Collection\UserCollection
     */
    public function fetchMembers($id);


    /**
     * Returns the list of user's groups.
     * 
     * @param integer $userId
     * @return \InoPerunApi\Entity\Collection\GroupCollection
     */
    public function fetchUserGroups($userId);


    /**
     * Adds the user to the group.
     * 
     * @param integer $userId
     * @param integer $groupId
     */
    public function addUserToGroup($userId, $groupId);


    /**
     * Removes the user from the group.
     * 
     * @param integer $userId
     * @param integer $groupId
     */
    public function removeUserFromGroup($userId, $groupId);
}