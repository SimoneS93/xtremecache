#Prestashop Xtreme cache module

Today I was thinking about Prestashop front office performance optimization and the lack of a full cache system came to mind (by full cache, I mean save the page html to file and serve that on subsequent requests, with no processing at all). 
In the first place I thought it was not possible, since Prestashop is higly dynamic and needs to update whenever a user interacts with the carts or the account.
But the I realized not all people visit our site logged in and for those the content
is almost static (at least in the short term, if we’re not updating our catalogue).
So the full cache system idea (with an expiration time near in the future) gained sense to me and I implemented a module just to do that.
It works hooking into *actionDispatcher* to process the incoming request as soon as possibile, before any database query or controller’s processing: if the user is not logged in and it finds a cached version of the requested page, it serves that page and aborts execution. 
You gain not only a better response time, but a lighter workload on the server, too! A win-win!
But to serve cached pages we need to store one, first. Prestashop doesn’t provide such an hook by default, so I created one in the Controller class, right before echoing the response to the browser.
You’ll find the module on the Prestashop official forum.
