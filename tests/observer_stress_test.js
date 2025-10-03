'use strict';

function isForceRefresh(options) {
  if (options === true) {
    return true;
  }
  if (options && options.force === true) {
    return true;
  }
  if (options && typeof options === 'object' && typeof options.preventDefault === 'function') {
    return true;
  }
  return false;
}

function createScheduler() {
  var pending = Object.create(null);
  var enqueue;
  if (typeof queueMicrotask === 'function') {
    enqueue = queueMicrotask;
  } else if (typeof Promise !== 'undefined') {
    enqueue = function (callback) {
      Promise.resolve()
        .then(callback)
        .catch(function (error) {
          setTimeout(function () {
            throw error;
          });
        });
    };
  } else {
    enqueue = function (callback) {
      setTimeout(callback, 0);
    };
  }
  return function schedule(key, callback) {
    if (pending[key]) {
      return;
    }
    pending[key] = true;
    enqueue(function () {
      pending[key] = false;
      callback();
    });
  };
}

function createMockContainer(name) {
  return {
    name: name,
    dataset: Object.create(null),
    counters: Object.create(null)
  };
}

function incrementCounter(container, key) {
  container.counters[key] = (container.counters[key] || 0) + 1;
}

function hydrate(container) {
  incrementCounter(container, 'hydrate');
}

function renderBlogDetail(container) {
  incrementCounter(container, 'blogDetail');
}

function renderEventsBlock(container) {
  incrementCounter(container, 'events');
}

function renderCalendarBlock(container) {
  incrementCounter(container, 'calendar');
}

var eventsIndicatorUpdates = 0;
function updateEventsCartIndicators() {
  eventsIndicatorUpdates += 1;
}

var blogLists = [createMockContainer('list-1'), createMockContainer('list-2')];
var blogDetails = [createMockContainer('detail-1')];
var calendarBlocks = [createMockContainer('calendar-1')];
var eventBlocks = [createMockContainer('event-1')];

var document = {
  querySelectorAll: function (selector) {
    switch (selector) {
      case '[data-blog-list]':
        return blogLists;
      case '[data-blog-detail]':
        return blogDetails;
      case '[data-calendar-block]':
        return calendarBlocks;
      case '[data-events-block]':
        return eventBlocks;
      default:
        return [];
    }
  }
};

function initBlogLists(options) {
  var force = isForceRefresh(options);
  var lists = document.querySelectorAll('[data-blog-list]');
  lists.forEach(function (container) {
    if (!force && container.dataset.sparkBlogListInitialized === 'true') {
      return;
    }
    container.dataset.sparkBlogListInitialized = 'true';
    hydrate(container);
  });
}

function initBlogDetails(options) {
  var force = isForceRefresh(options);
  var details = document.querySelectorAll('[data-blog-detail]');
  details.forEach(function (container) {
    if (!force && container.dataset.sparkBlogDetailInitialized === 'true') {
      return;
    }
    container.dataset.sparkBlogDetailInitialized = 'true';
    renderBlogDetail(container);
  });
}

function initCalendarBlocks(options) {
  var force = isForceRefresh(options);
  var blocks = document.querySelectorAll('[data-calendar-block]');
  blocks.forEach(function (block) {
    if (!force && block.dataset.sparkCalendarInitialized === 'true') {
      return;
    }
    block.dataset.sparkCalendarInitialized = 'true';
    renderCalendarBlock(block);
  });
}

function initEventsBlocks(options) {
  var force = isForceRefresh(options);
  var shouldUpdateIndicators = force;
  var blocks = document.querySelectorAll('[data-events-block]');
  blocks.forEach(function (block) {
    if (!force && block.dataset.sparkEventsInitialized === 'true') {
      return;
    }
    block.dataset.sparkEventsInitialized = 'true';
    shouldUpdateIndicators = true;
    renderEventsBlock(block);
  });
  if (shouldUpdateIndicators) {
    updateEventsCartIndicators();
  }
}

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

async function runSchedulerStressTest() {
  var schedule = createScheduler();
  var counts = {
    blog: 0,
    events: 0
  };
  for (var i = 0; i < 1000; i += 1) {
    schedule('blogLists', function () {
      counts.blog += 1;
    });
  }
  for (var j = 0; j < 500; j += 1) {
    schedule('events', function () {
      counts.events += 1;
    });
  }
  await new Promise(function (resolve) {
    setTimeout(resolve, 0);
  });
  assert(counts.blog === 1, 'Blog refresh should be debounced to a single run.');
  assert(counts.events === 1, 'Events refresh should be debounced to a single run.');
}

function runInitializationStressTest() {
  initBlogLists();
  initBlogLists();
  assert(blogLists[0].counters.hydrate === 1, 'Existing blog list should only hydrate once.');
  assert(blogLists[1].counters.hydrate === 1, 'Existing blog list should only hydrate once.');
  var newList = createMockContainer('list-3');
  blogLists.push(newList);
  initBlogLists();
  assert(newList.counters.hydrate === 1, 'New blog list should hydrate immediately.');
  initBlogLists({ force: true });
  assert(blogLists.every(function (container) {
    return container.counters.hydrate === 2;
  }), 'Force refresh should rehydrate all blog lists.');

  initBlogDetails();
  initBlogDetails();
  assert(blogDetails[0].counters.blogDetail === 1, 'Blog detail should only render once.');
  var newDetail = createMockContainer('detail-2');
  blogDetails.push(newDetail);
  initBlogDetails();
  assert(newDetail.counters.blogDetail === 1, 'New blog detail should render immediately.');

  initCalendarBlocks();
  initCalendarBlocks();
  assert(calendarBlocks[0].counters.calendar === 1, 'Calendar block should only render once.');
  var newCalendar = createMockContainer('calendar-2');
  calendarBlocks.push(newCalendar);
  initCalendarBlocks();
  assert(newCalendar.counters.calendar === 1, 'New calendar block should render immediately.');

  eventsIndicatorUpdates = 0;
  initEventsBlocks();
  assert(eventBlocks[0].counters.events === 1, 'Event block should render once.');
  assert(eventsIndicatorUpdates === 1, 'Indicators should update when events render.');
  initEventsBlocks();
  assert(eventBlocks[0].counters.events === 1, 'Event block should not re-render without force.');
  assert(eventsIndicatorUpdates === 1, 'Indicators should not update without changes.');
  var newEvent = createMockContainer('event-2');
  eventBlocks.push(newEvent);
  initEventsBlocks();
  assert(newEvent.counters.events === 1, 'New event block should render immediately.');
  assert(eventsIndicatorUpdates === 2, 'Indicators should update when new event blocks render.');
}

async function main() {
  await runSchedulerStressTest();
  runInitializationStressTest();
  console.log('Observer stress test completed successfully.');
}

main().catch(function (error) {
  console.error(error);
  process.exit(1);
});
