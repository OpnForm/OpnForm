<?php
// Local model double: the generated promotion program still owns its real
// history/lease files, but no Coolify database, SSH or Docker can be reached.
namespace App\Models {
    class Service
    {
        public static $frontend = null;
        public $server_id = 5;
        public $uuid = 'ssssssssssssssssssssssss';
        public $server;
        public $environment;
        public $docker_compose_raw;

        public function __construct()
        {
            $this->server = (object) ['ip' => '100.65.151.96'];
            $this->environment = (object) ['project' => (object) ['id' => 4, 'team_id' => 0]];
            $this->docker_compose_raw = json_encode(['services' => array_fill_keys(
                ['api', 'frontend', 'jobs', 'scheduler', 'migrate'], ['image' => 'previous-image']
            )]);
            if (self::$frontend !== null) {
                $raw = json_decode($this->docker_compose_raw, true);
                $raw['services']['frontend'] = self::$frontend;
                $this->docker_compose_raw = json_encode($raw);
            }
        }

        public static function where(...$arguments) { return new self; }
        public function sole() { return $this; }
        public function save() {}
        public function parse() {}
        public function saveComposeConfigs() {}
    }
}
