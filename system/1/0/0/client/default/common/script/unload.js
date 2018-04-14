//clearInterval($system.task.interval); //Clear periodic task scheduler
clearInterval($system.user.refresh) //Stop refreshing cookie expire time

//Remove the stylesheets
$system.style.remove($id, 'common.css')
$system.style.remove($id, $system.browser.engine + '.css')

//Remove the mouse motion events
$system.event.remove(document.body, 'onmousemove', $system.motion.move)
$system.event.remove(document.body, 'onmouseup', $system.motion.stop)

$system.event.remove(document.body, 'onclick', $system.tip.clear)
$system.event.remove(document.body, 'onclick', $system.user.active)

$system.event.remove(window, 'onresize', $system.image.fit)
