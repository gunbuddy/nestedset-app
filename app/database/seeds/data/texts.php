<?php

return array(
    'documentation' => <<<'EOT'
Say hi to Laravel 4 extension that will allow to create and manage hierarchies in
your database out-of-box. You can:

*   Create multi-level menus and select items of specific level;
*   Create categories for the store with no limit of nesting level, query for
    descendants and ancestors;
*   Forget about performance issues!
EOT

    , 'installation' => <<<'EOT'
The package can be installed using Composer, just include it into `required` 
section of your `composer.json` file:

    "required": {
        "kalnoy/nestedset": "1.0-beta"
    }
    
Hit `composer update` in the terminal, and you are ready to go next!
EOT

    , 'basic_usage' => <<<'EOT'
### Schema

Storing hierarchies in a database requires additional columns for the table, so these
fields need to be included in the migration. Also, the root node is required.
So, basic migration looks like this:

    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Kalnoy\Nestedset\NestedSet;

    class CreateCategoriesTable extends Migration {

        /**
         * Run the migrations.
         *
         * @return void
         */
        public function up()
        {
            Schema::create('categories', function(Blueprint $table) {
                $table->increments('id');
                $table->string('title');
                $table->timestamps();

                NestedSet::columns($table);
            });

            // The root node is required
            NestedSet::createRoot('categories', array(
                'title' => 'Store',
            ));
        }

        /**
         * Reverse the migrations.
         *
         * @return void
         */
        public function down()
        {
            Schema::drop('categories');
        }
    }

### The model

The next step is to create `Eloquent` model. I prefer [Jeffrey Way's generators][1],
but you can stick to whatever you prefer, just make shure that model is extended 
from `\Kalnoy\Nestedset\Node`, like here:

[1]: https://github.com/JeffreyWay/Laravel-4-Generators

    <?php

    class Category extends \Kalnoy\Nestedset\Node {}

### Queries

You can insert nodes using several methods:

    $node = new Category(array('title' => 'TV\'s'));
    $target = Category::root();

    $node->appendTo($target)->save();
    $node->prependTo($target)->save();

The parent can be changed via mass asignment:

    // The equivalent of $node->appendTo(Category::find($parent_id))
    $node->parent_id = $parent_id;
    $node->save();

You can insert the node right next to or before the other node:

    $srcNode = Category::find($src_id);
    $targetNode = Category::find($target_id);

    $srcNode->after($targetNode)->save();
    $srcNode->before($targetNode)->save();

_Ancestors_ can be obtained in two ways:

    // Target node will not be included into result since it is already available
    $path = $node->ancestors()->get();

or using the scope:

    // Target node will be included into result
    $path = Category::ancestorsOf($nodeId)->get();

_Descendants_ can easily be retrieved in this way:

    $descendants = $node->descendants()->get();

This method returns query builder, so you can apply any constraints or eager load
some relations. 

There are few more methods:

*   `siblings()` for querying siblings of the node;
*   `nextSiblings()` and `prevSiblings()` to query nodes after and before the node
    respectively.

Nodes can be provided with _nesting level_ if scope `withDepth` is applied:

    // Each node instance will recieve 'depth' attribute with depth level starting at
    // zero for the root node.
    $nodes = Category::withDepth()->get();

Using `depth` attribute it is possible to get nodes with maximum level of nesting:

    $menu = Menu::withDepth()->having('depth', '<=', 2)->get();

The root node can be filtered out using scope `withoutRoot`:

    $nodes = Category::withoutRoot()->get();

Nothing changes when you need to remove the node:

    $node->delete();

### Relations

There are two relations provided by `Node`: _children_ and _parent_.

### Insertion, re-insertion and deletion of nodes

Operations such as insertion and deletion of nodes imply extra queries
before node is actually saved. That is why if something goes wrong, the whole tree
might be broken. To avoid such situations, each call of `save()` has to be enclosed 
in the transaction.
EOT

    , 'howtos' => <<<'EOT'
Here some recipes can be found.
EOT

    , 'updown' => <<<'EOT'
Sometimes there is need to move nodes around while remaining in boundaries of 
the parent.

To move node down, this snippet can be used:

    if ($sibling = $node->nextSiblings()->first())
    {
        $node->after($sibling)->save();
    }

Moving up is a little bit trickier:

    if ($sibling = $node->prevSiblings()->reversed()->first())
    {
        $node->before($sibling)->save();
    }

To move node up we need to insert it before node that is right at the top of it.
If we use `$node->prevSiblings()->first()` we'll get the first child of the parent 
since all nodes are ordered by fixed values. We apply `reversed()` scope to reverse
default order.
EOT

    , 'advanced' => <<<'EOT'
In this section you can find some advanced techniques.
EOT

    , 'default_order' => <<<'EOT'
Nodes are ordered by lft column unless there is `limit` or `offset` is provided,
or when user uses `orderBy`.
EOT

    , 'custom_collection' => <<<'EOT'
This package also provides custom collection, which has two additional functions:
`toDictionary` and `toTree`. The latter builds a tree from the list of nodes just like
if you would query only root node with all of the children, and children of that
children, etc. This function restores parent-child relations, so the resulting collection
will contain only top-level nodes and each of this node will have `children` relation
filled. The interesting thing is that when some node is rejected by a query constraint,
whole subtree will be rejected during building the tree.

Consider the tree of categories:

    Catalog
    - Mobile
    -- Apple
    -- Samsung
    - Notebooks
    -- Netbooks
    --- Apple
    --- Samsung
    -- Ultrabooks

Let's see what we have in PHP:

    $tree = Category::where('title', '<>', 'Netbooks')->withoutRoot()->get()->toTree();
    echo $tree;

This is what we are going to get:

    [{
        "title": "Mobile",
        "children": [{ "title": "Apple", "children": [] }, { "title": "Samsung", "children": [] }]
    },

    {
        "title": "Notebooks",
        "children": [{ "title": "Ultrabooks", "children": [] }]
    }];

Even though the query returned all nodes but _Netbooks_, the resulting tree does 
not contain any child from that node. This is very helpful when nodes are soft deleted. 
Active children of soft deleted nodes will inevitably show up in query results, 
which is not desired in most situations.
EOT

, 'multiple_insertion' => <<<'EOT'
_DO NOT MAKE MULTIPLE INSERTIONS DURING SINGLE HTTP REQUEST_

Since when node is inserted or re-inserted tree is changed in database, nodes
that are already loaded might also have changed and need to be refreshed. This
doesn't happen automatically with exception of one scenario.

Consider this example:

    $nodes = Category::whereIn('id', Input::get('selected_ids'))->get();
    $target = Category::find(Input::get('target_id'));

    foreach ($nodes as $node) {
        $node->appendTo($target)->save();
    }

This is the example of situation when user picks up several nodes and moves them
into new parent. When we call `appendTo` nothing is really changed but internal
variables. Actual transformations are performed when `save` is called. When that
happens, values of internal variables are definately changed for `$target` and
might change for some nodes in `$nodes` list. But this changes happen in database
and do not reflect into memory for loaded nodes. Calling `appendTo` with outdated 
values brakes the tree.

In this case only values of `$target` are crucial. The system always updates crucial
attributes of parent of node being saved. Since `$target` becomes new parent for
every node, the data of that node will always be up to date and this example will
work just fine.

_THIS IS THE ONLY CASE WHEN MULTIPLE NODES CAN BE INSERTED AND/OR RE-INSERTED 
DURING SINGLE HTTP REQUEST WITHOUT REFRESHING DATA_
EOT

);