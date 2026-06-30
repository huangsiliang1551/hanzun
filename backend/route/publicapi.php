<?php

declare(strict_types=1);

return [
    ['POST', '/api/visitor-events', 'app\\publicapi\\controller\\AiChatController@track', null],
    ['POST', '/api/ai/chat', 'app\\publicapi\\controller\\AiChatController@chat', null],
    ['POST', '/api/ai/session', 'app\\publicapi\\controller\\AiChatController@session', null],
    ['GET', '/api/site/bootstrap', 'app\\publicapi\\controller\\ContentController@bootstrap', null],
    ['GET', '/api/site/navigation', 'app\\publicapi\\controller\\ContentController@navigation', null],
    ['GET', '/api/site/homepage', 'app\\publicapi\\controller\\ContentController@homepage', null],
    ['GET', '/api/site/ads', 'app\\publicapi\\controller\\ContentController@ads', null],
    ['GET', '/api/site/about', 'app\\publicapi\\controller\\ContentController@about', null],
    ['GET', '/api/site/contact', 'app\\publicapi\\controller\\ContentController@contact', null],
    ['GET', '/api/site/products', 'app\\publicapi\\controller\\ContentController@products', null],
    ['GET', '/api/site/products/{slug}', 'app\\publicapi\\controller\\ContentController@productDetail', null],
    ['GET', '/api/site/solutions', 'app\\publicapi\\controller\\ContentController@solutions', null],
    ['GET', '/api/site/solutions/{slug}', 'app\\publicapi\\controller\\ContentController@solutionDetail', null],
    ['GET', '/api/site/articles', 'app\publicapi\controller\ContentController@articles', null],
    ['GET', '/api/site/articles/{slug}', 'app\publicapi\controller\ContentController@articleDetail', null],
    ['GET', '/api/site/news', 'app\publicapi\controller\ContentController@newsList', null],
    ['GET', '/api/site/news/{slug}', 'app\publicapi\controller\ContentController@newsDetail', null],
    ['GET', '/api/site/cases', 'app\publicapi\controller\ContentController@caseList', null],
    ['GET', '/api/site/cases/{slug}', 'app\publicapi\controller\ContentController@caseDetail', null],
    ['GET', '/api/site/pages/{slug}', 'app\\publicapi\\controller\\ContentController@pageDetail', null],
    ['POST', '/api/site/lead', 'app\publicapi\controller\ContentController@lead', null],
    ['POST', '/api/site/pageview', 'app\publicapi\controller\ContentController@pageview', null],
    ['GET', '/robots.txt', 'app\publicapi\controller\ContentController@robotsTxt', null],
    ['GET', '/sitemap.xml', 'app\publicapi\controller\ContentController@sitemapXml', null],
];
