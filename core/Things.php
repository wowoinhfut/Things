<?php

/**
 * Main class of Things
 * User: wowo
 * Date: 16/12/5 下午7:11
 */
class Things {

    protected $config;

    protected $got_url = array();

    protected $visited_url = array();

    protected $visiting_url = '';

    protected $download_dir = '/Users/zhenggui/Downloads/pictures/';

    public function __construct() {
        $config = array();
        require 'config/config.php';
        $this->config = $config;
        #check domain
        $ch = curl_init();
        $visit_url = 'http://' . $this->config['domain'];
        curl_setopt($ch, CURLOPT_URL, $visit_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $check_domain = curl_exec($ch);
        if (!$check_domain) {
            foreach (explode(',', $this->config['try_index']) as $index) {
                $visit_url = rtrim($this->config['domain'], '/') . '/' . $index;
                curl_setopt($ch, CURLOPT_URL, $visit_url);
                $check_domain = curl_exec($ch);
                if ($check_domain) {
                    continue;
                }
            }
        }
        if (!$check_domain) {
            error_log('domain can not be things');
            exit(1);
        }
    }

    public function start() {
        $this->got_url[] = 'http://' . $this->config['domain'] . '/' . $this->config['start_url'];
        while ($visit_url = array_shift($this->got_url)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $visit_url);
            $output = curl_exec($ch);
            $this->visiting_url = $visit_url;
            $this->process_url_zngirls($output);
            $this->process_data($output);
            $this->visited_url[] = $visit_url;
            if (count($this->visited_url) > $this->config['max_url_num']) {
                break;
            }
        }
        print_r($this->visited_url);
    }

    /**
     * 处理url
     */
    private function process_url($output) {
        preg_match_all('/<a.*?\shref=["\']+(.*?)["\']+/', $output, $result);
        #过滤掉已记录过的url
        foreach (array_unique($result[1]) as $url) {
            if ('#' == $url) {
                continue;
            }
            if (strpos($url, 'http://') === FALSE) {
                $url = 'http://' . $this->config['domain'] . '/' . ltrim($url, '/');
            } else {
                $url_data = parse_url($url);
                if ($url_data['host'] !== $this->config['domain']) {
                    continue;
                }
            }
            if (!in_array($url, $this->visited_url) && !in_array($url, $this->got_url)) {
                $this->add_url($this->got_url, $url);
            }
        }
    }

    private function process_url_zngirls($output) {
        preg_match_all('/<a.*?\shref=["\']+(\/g\/\d{1,7}\/\d{1,3}\.html)["\']/', $output, $same_topic);
        preg_match_all('/<a.*?\shref=["\']+(\/g\/\d{1,7}\/)["\']/', $output, $other_topic);
        #过滤掉已记录过的url
        $same_topic = array_reverse(array_unique($same_topic[1]));
        foreach (array_unique($same_topic) as $url) {
            #起始url就是第一页,为了避免重复下载第一页,需要将1.html过滤掉
            if ('#' == $url || strpos($url, '/1.html') > 0) {
                continue;
            }
            if (strpos($url, 'http://') === FALSE) {
                $url = 'http://' . $this->config['domain'] . '/' . ltrim($url, '/');
            } else {
                $url_data = parse_url($url);
                if ($url_data['host'] !== $this->config['domain']) {
                    continue;
                }
            }
            if (!in_array($url, $this->visited_url) && !in_array($url, $this->got_url)) {
                array_unshift($this->got_url, $url);
            }
        }

        foreach (array_unique($other_topic[1]) as $url) {
            if ('#' == $url) {
                continue;
            }
            if (strpos($url, 'http://') === FALSE) {
                $url = 'http://' . $this->config['domain'] . '/' . ltrim($url, '/');
            } else {
                $url_data = parse_url($url);
                if ($url_data['host'] !== $this->config['domain']) {
                    continue;
                }
            }
            if (!in_array($url, $this->visited_url) && !in_array($url, $this->got_url)) {
                $this->got_url[] = $url;
            }
        }
    }

    #根据配置的遍历方式,将url添加到got_url中
    private function add_url($urls, $url) {
        switch ($this->config['traverse_method']) {
            case 'wide':
                array_push($urls, $url);
                break;
            case 'deep':
                array_unshift($urls, $url);
                break;
        }
    }

    private function process_data($output) {
        $img_regex = '/<img.*?\ssrc=["\']+(.*?)["\']+/';
        preg_match_all($img_regex, $output, $result);
        if (!empty($result[1])) {
            foreach ($result[1] as $url) {
                $this->download_data($url);
            }
        }
        return;
    }

    private function download_data($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        #添加referer,破解防盗链
        curl_setopt($ch, CURLOPT_REFERER, $this->visiting_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        ob_start();
        curl_exec($ch);
        $return_content = ob_get_contents();
        $file_size = ob_get_length();
        #太小的图片就不要了
        if ($this->config['pic_size'] && $file_size < $this->config['pic_size']) {
            return;
        }
        ob_end_clean();
        curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $filename = $this->config['download_dir'] . microtime(TRUE) * 10000 . '.jpg';
        $fp = @fopen($filename, "a");
        fwrite($fp, $return_content);
    }

}