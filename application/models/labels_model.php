<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Labels_model extends Plain_Model
{

    public $sort = 'created_on ASC';


    public function __construct()
    {
        // Call the Model constructor
        parent::__construct();

        // Set data types
        $this->data_types = array(
            'smart_label_id'  =>  'numeric',
            'user_id'         =>  'numeric',
            'name'            =>  'string',
            'domain'          =>  'domain',
            'path'            =>  'string',
            'smart_key'       => 'md5',
            'active'          => 'bool',
            'slug'            => 'string',
            'created_on'      => 'datetime'
        );

    }

    public function create($options=array())
    {
        $smart_label = (isset($options['smart_label']) && ! empty($options['smart_label'])) ? true : false;

        // If a smart label, set the required fields
        if ($smart_label === true) {
            $required = array('smart_label_id', 'user_id', 'name', 'domain');
        }
        else {
            $required = array('name');
        }

        $valid  = validate($options, $this->data_types, $required);

        // Make sure all the options are valid
        if ($valid === true) {

            // If smart label, create MD5 hash of domain and path
            if ($smart_label == true) {
                $md5                  = md5($options['domain'] . $options['path']);
                $where                = "labels.smart_key = '" . $md5 . "' AND labels.user_id = '" . $options['user_id'] . "'";
                $options['smart_key'] = $md5;
            }
            else {
                $options['slug'] = generateSlug($options['name']);
                $where           = "labels.slug = '" . $options['slug'] . "' labels.user_id IS NULL";
            }

            // See if this record already exists
            $label = $this->read($where, 1, 1);

            // If not, add it
            if (! isset($label->label_id)) {
                $options['created_on'] = date('Y-m-d H:i:s');
                $q                     = $this->db->insert_string($this->table, $options);
                $res                   = $this->db->query($q);

                // Check for errors
                $this->sendException();

                // If good, return full label
                if ($res === true) {
                    $label_id = $this->db->insert_id();
                    return $this->read($label_id);
                }

                // Else return error
                return $this->formatErrors('Label could not be added. Please try again.');
            }

            // If already exists, just return it
            return $label;

        }

        return $this->formatErrors($valid);
    }

    protected function formatResults($labels)
    {
        foreach ($labels as $k => $label) {
            $labels[$k]->type = (empty($label->smart_label_id)) ? 'label' : 'smart';

            // Create different information sets for label vs. smart_label
            if ($labels[$k]->type =='smart') {
                $labels[$k]->settings         = new stdClass;
                $labels[$k]->settings->domain = $labels[$k]->smart_label_domain;
                $labels[$k]->settings->path   = $labels[$k]->smart_label_path;

                $labels[$k]->settings->label        = new stdClass;
                $labels[$k]->settings->label->name  = $labels[$k]->smart_label_name;
                $labels[$k]->settings->label->slug  = $labels[$k]->smart_label_slug;
                $labels[$k]->settings->label->id   = $labels[$k]->smart_label_id;

                // Unset some shiz
                unset($labels[$k]->name);
                unset($labels[$k]->slug);
            }

            // Unset all smart_label keys
            unset($labels[$k]->smart_label_id);
            unset($labels[$k]->smart_label_domain);
            unset($labels[$k]->smart_label_path);
            unset($labels[$k]->smart_label_name);
            unset($labels[$k]->smart_label_slug);
        }

        return $labels;
    }

    public function readComplete($where, $limit=1, $page=1, $start=null)
    {
        $id         = (is_numeric($where)) ? $where : null;
        $where      = (is_numeric($where)) ? $this->table . '.' . $this->id_column . " = '$where'" : trim($where);
        $page       = (is_numeric($page) && $page > 0) ? $page : 1;
        $limit      = ((is_numeric($limit) && $limit > 0) || $limit == 'all') ? $limit : 1;
        $start      = (! is_null($start)) ? $start : $limit * ($page - 1);
        $q_limit    = ($limit != 'all') ? ' LIMIT ' . $start . ',' . $limit : null;
        $sort       = (! empty($this->sort)) ? ' ORDER BY l.' . $this->sort : null;

        // Stop, query time
        $labels = $this->db->query("
            SELECT
            labels.label_id, labels.smart_label_id, labels.name, labels.slug, labels.domain AS smart_label_domain, labels.path AS smart_label_path, labels.active,
            l.name AS smart_label_name, l.slug AS smart_label_slug
            FROM labels
            LEFT JOIN labels AS l ON labels.smart_label_id = l.label_id
            WHERE " . $where . " GROUP BY " . $this->table . '.' . $this->id_column . $sort . $q_limit
        );

        // Check for errors
        $this->sendException();

        // Now format the group names and ids
        if ($labels->num_rows() > 0) {
            $labels = $this->formatResults($labels->result());
            return ($limit == 1) ? $labels[0] : $labels;
        }

        return false;
    }

}