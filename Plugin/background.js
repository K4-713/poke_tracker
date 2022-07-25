// background.js
chrome.runtime.onMessage.addListener(function(request, sender, sendResponse) {
  //got a message
  var log = [];
  log.push("Attempting to save " + request.file + " to " + request.name);
  chrome.downloads.download({url: request.file, filename: request.name, conflictAction: 'overwrite'},
    function (id) {
      if (chrome.runtime.lastError){
        console.log("Problem here");
        log.push("Error downloading. Maybe some context next:");
        log.push(chrome.runtime.lastError.message);
      }
  });

  sendResponse({message: log});
});

