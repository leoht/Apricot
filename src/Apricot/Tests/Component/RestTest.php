<?php

namespace Apricot\Tests\Component;

use Apricot\Apricot;

class RestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Apricot\Component\Rest::resource
     */
    public function testResourceRouteCreated()
    {
        Apricot::reset();

        Apricot::resource('posts', function ()
        {
            Apricot::index(function ()
            {
                echo "Index";
            });
        });

        $this->assertTrue('Index' === Apricot::browse('/posts'));
    }

    /**
     * @covers Apricot\Component\Rest::resource
     */
    public function testDeepResourceRouteCreated()
    {
        Apricot::reset();

        Apricot::resource('posts', function ()
        {
            Apricot::resource('comments', function ()
            {
                Apricot::index(function ($post_id)
                {
                    echo "Post $post_id comments";
                });

                Apricot::show(function ($post_id, $id)
                {
                    echo "Comment $id of post $post_id";
                });
            });
        });

        $this->assertTrue('Post 4 comments' === Apricot::browse('/posts/4/comments'));
        $this->assertTrue('Comment 10 of post 4' === Apricot::browse('/posts/4/comments/10'));
    }
}
