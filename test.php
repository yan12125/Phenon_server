<pre>
<?php
$result = exec('python3 /srv/http/test.py 2>&1', $output, $ret);
print_r($output);
echo $ret;
?>
</pre>
