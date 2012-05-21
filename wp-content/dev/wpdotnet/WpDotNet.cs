using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using PHP.Core;

namespace Devsense.WordPress.Plugins.WpDotNet
{
    public class WpDotNet
    {

        /// <summary>
        /// Plugin loader.
        /// </summary>
        public static void Load(string plugin_slug)
        {
            var context = ScriptContext.CurrentContext;

            // zlib:
            if (context.ApplicationContext.GetExtensionImplementor("zlib") == null)
                Zlib.Zlib.Load(context);

            // xml:
            // - the whole xml extension is needed
            if (context.ApplicationContext.GetExtensionImplementor("xml") == null)
            {
                //deactivate rss feeds in dashboard
                AdminSectionUtils.CleanDashboard(context);
            }

            // mbstring:
            if (context.ApplicationContext.GetExtensionImplementor("mbstring") == null)
                Mbstring.Mbstring.Load(context);

            // libxml:
            // - LIBXML_NOERROR, LIBXML_NOWARNING

            // Plugin API / update hook
            PluginUpdate.Load(context, plugin_slug);
        }
    }
}
