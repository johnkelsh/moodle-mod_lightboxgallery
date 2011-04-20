Event.observe(window, 'load', function() {
  $$('.overlay').each( function(overlay) {
    overlay.setStyle({ 'opacity' : 0.8 });
    overlay.observe('mouseover', function(event) {
      new Effect.Opacity(overlay, { 'duration' : 0.3, 'to' : 1.0 });
    });
    overlay.observe('mouseout', function(event) {
      new Effect.Opacity(overlay, { 'duration' : 0.3, 'to' : 0.8 });
    });
  });
}, false);
