<?php
/**
 * @interface   Request Handler
 *
 * Interface for all request handlers for the methods
 * they must have
 *
 * @author  Alex Cummaudo
 * @date    2013-11-30
 */
interface iRequestHandler
{
    public function execute(array $data);
    public function get_id();
    public function validate_data(array $data);
}
?>