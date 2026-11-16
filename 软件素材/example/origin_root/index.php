<?php
/**
 * 模拟源站 - 动态页面（带时间戳，模拟原站动态内容）
 * CDN 回源时会抓取此页面并缓存。
 */
header('Content-Type: text/html; charset=UTF-8');
header('X-Origin-Server: origin-1');
echo "<!DOCTYPE html>\n";
echo "<html lang=\"zh-CN\">\n";
echo "<head><meta charset=\"UTF-8\"><title>源站首页</title></head>\n";
echo "<body>\n";
echo "<h1>源站首页</h1>\n";
echo "<p>页面生成时间：" . date('Y-m-d H:i:s') . "</p>\n";
echo "<p>这是原站的动态内容，CDN 回源后会缓存此页面。</p>\n";
echo "</body>\n";
echo "</html>\n";
